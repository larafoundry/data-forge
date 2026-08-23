<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Attributes\Max;
use Axiom\DataForge\Attributes\Min;
use Axiom\DataForge\Attributes\StringLength;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Schema\TypeSpec;
use Closure;
use Illuminate\Contracts\Validation\Rule as LegacyValidationRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;
use Throwable;

/**
 * @phpstan-type LaravelStringableRule Dimensions|ExcludeIf|In|NotIn|ProhibitedIf|RequiredIf
 * @phpstan-type SupportedValidationRule ValidationRule|LegacyValidationRule|LaravelStringableRule
 * @phpstan-type RuleValue string|SupportedValidationRule
 * @phpstan-type BuiltRule string|Closure|SupportedValidationRule
 */
final class DtoRuleBuilder
{
    private const DATABASE_BACKED_RULE_MESSAGE = 'Database-backed validation rules such as Rule::exists() and Rule::unique() are unsupported without an explicit validation factory integration.';

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,array<int,RuleValue>>  $customRules
     * @param  array<string,mixed>  $attributes
     * @return array<string, array<int,BuiltRule>>
     *
     * @throws InspectionException
     */
    public static function build(
        DtoInspector $inspector,
        array $customRules,
        array $attributes,
        bool $includeTypeRules = true,
        bool $includeAttributeRules = true,
    ): array {
        /** @var array<string, array<int,BuiltRule>> $rules */
        $rules = [];

        foreach ($inspector->getRequiredKeys() as $key) {
            $rules[$key][] = 'required';
        }

        foreach ($inspector->getAcceptedKeys() as $key) {
            if (! in_array($key, $inspector->getRequiredKeys(), true)
                && $inspector->isTypeAcceptedForKey($key, null)
            ) {
                $rules[$key][] = 'nullable';
            }

            if ($includeTypeRules) {
                $rules[$key][] = static function (string $attribute, mixed $value, Closure $fail) use ($inspector, $key) {
                    if (! $inspector->isTypeAcceptedForKey($key, $value)) {
                        $fail("The $attribute field has an invalid type.");
                    }
                };
            }

            if ($includeAttributeRules) {
                $attributeRules = self::buildAttributeRules($inspector, $key);
                if ($attributeRules !== []) {
                    $rules[$key] = array_merge($rules[$key] ?? [], $attributeRules);
                }
            }
        }

        $acceptedKeys = $inspector->getAcceptedKeys();

        foreach ($customRules as $field => $fieldRules) {
            if (! is_string($field)) {
                throw new InspectionException('Validation rule field names must be strings.');
            }

            $rootField = explode('.', $field, 2)[0];
            // Check if the field exists in the DTO before adding rules for it
            if (! in_array($rootField, $acceptedKeys, true)) {
                throw new InspectionException(
                    "Cannot add validation rules for field '$field' because root field '$rootField' does not exist in the DTO."
                );
            }

            $fieldRules = self::addNumericRuleForNumericType($inspector, $field, $fieldRules);
            $rules[$field] = array_merge($rules[$field] ?? [], $fieldRules);
        }

        foreach (array_keys($attributes) as $key) {
            if (! isset($rules[$key])) {
                $rules[$key][] = 'nullable';
            }
        }

        return $rules;
    }

    /**
     * @param  string|array<mixed, mixed>|object  $raw
     * @return array<int,RuleValue>
     *
     * @throws InspectionException
     */
    public static function normalizeFieldRules(string|array|object $raw): array
    {
        if (is_string($raw)) {
            return array_map(
                static function (string $rule): string {
                    $rule = trim($rule);
                    self::assertSupportedStringRule($rule);

                    return $rule;
                },
                explode('|', $raw)
            );
        }

        if (is_object($raw)) {
            self::assertSupportedLaravelRuleObject($raw);

            return [$raw];
        }

        /** @var list<RuleValue> $result */
        $result = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                self::assertSupportedStringRule($item);
                $result[] = $item;

                continue;
            }

            if (is_object($item)) {
                self::assertSupportedLaravelRuleObject($item);
                $result[] = $item;

                continue;
            }

            throw new InspectionException(
                'Validation rules must be a pipe-string, a supported Laravel rule object, or a flat array of strings and supported Laravel rule objects.'
            );
        }

        return $result;
    }

    public static function stringValueForLaravelStringableRule(object $rule): ?string
    {
        if ($rule instanceof Dimensions) {
            return $rule->__toString();
        }

        if ($rule instanceof ExcludeIf) {
            return $rule->__toString();
        }

        if ($rule instanceof In) {
            return $rule->__toString();
        }

        if ($rule instanceof NotIn) {
            return $rule->__toString();
        }

        if ($rule instanceof ProhibitedIf) {
            return $rule->__toString();
        }

        if ($rule instanceof RequiredIf) {
            return $rule->__toString();
        }

        return null;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<int,RuleValue>  $fieldRules
     * @return array<int,RuleValue>
     */
    private static function addNumericRuleForNumericType(DtoInspector $inspector, string $key, array $fieldRules): array
    {
        if (! (self::typeForPath($inspector, $key)?->isNumeric() ?? false)) {
            return $fieldRules;
        }

        if (! self::containsNumericSizeRule($fieldRules) || self::containsExplicitSizeTypeRule($fieldRules)) {
            return $fieldRules;
        }

        array_unshift($fieldRules, 'numeric');

        return $fieldRules;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     *
     * @throws InspectionException
     */
    private static function typeForPath(DtoInspector $inspector, string $path): ?TypeSpec
    {
        $parts = explode('.', $path);
        $currentInspector = $inspector;
        $type = null;

        foreach ($parts as $index => $part) {
            if ($part === '*' || ctype_digit($part)) {
                continue;
            }

            $type = $currentInspector->getTypeSpecForKey($part);
            if ($type === null || $index === array_key_last($parts)) {
                return $type;
            }

            $arrayItemClass = $currentInspector->getArrayItemClassForKey($part);
            if ($arrayItemClass !== null) {
                $currentInspector = new DtoInspector($arrayItemClass);

                continue;
            }

            $typeName = $type->namedClass();
            if ($typeName === null) {
                return null;
            }

            if (! DtoClass::isDto($typeName)) {
                return null;
            }

            $currentInspector = new DtoInspector($typeName);
        }

        return $type;
    }

    /**
     * @param  array<int,RuleValue>  $fieldRules
     */
    private static function containsNumericSizeRule(array $fieldRules): bool
    {
        foreach ($fieldRules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $ruleName = self::ruleName($rule);
            if (in_array($ruleName, ['min', 'max', 'between', 'gt', 'gte', 'lt', 'lte'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,RuleValue>  $fieldRules
     */
    private static function containsExplicitSizeTypeRule(array $fieldRules): bool
    {
        foreach ($fieldRules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $ruleName = self::ruleName($rule);
            if (in_array($ruleName, ['numeric', 'integer', 'decimal', 'string', 'array', 'file'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function ruleName(string $rule): string
    {
        return mb_strtolower(explode(':', trim($rule), 2)[0]);
    }

    /**
     * @throws InspectionException
     */
    private static function assertSupportedStringRule(string $rule): void
    {
        if (in_array(self::ruleName($rule), ['exists', 'unique'], true)) {
            throw new InspectionException(self::DATABASE_BACKED_RULE_MESSAGE);
        }
    }

    /**
     * @phpstan-assert SupportedValidationRule $rule
     *
     * @throws InspectionException
     */
    private static function assertSupportedLaravelRuleObject(object $rule): void
    {
        if ($rule instanceof Closure) {
            throw new InspectionException('Closure validation rules are unsupported.');
        }

        if ($rule instanceof Exists || $rule instanceof Unique) {
            throw new InspectionException(self::DATABASE_BACKED_RULE_MESSAGE);
        }

        if ($rule instanceof ValidationRule || $rule instanceof LegacyValidationRule || self::isSupportedLaravelStringableRule($rule)) {
            return;
        }

        throw new InspectionException(
            'Validation rule objects must implement Illuminate\Contracts\Validation\ValidationRule, Illuminate\Contracts\Validation\Rule for legacy Laravel compatibility, or be one of Laravel\'s supported stringable rule builders.'
        );
    }

    private static function isSupportedLaravelStringableRule(object $rule): bool
    {
        return $rule instanceof Dimensions
            || $rule instanceof ExcludeIf
            || $rule instanceof In
            || $rule instanceof NotIn
            || $rule instanceof ProhibitedIf
            || $rule instanceof RequiredIf;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @return array<int,string>
     *
     * @throws InspectionException
     */
    private static function buildAttributeRules(DtoInspector $inspector, string $key): array
    {
        $property = $inspector->getReflectionProperty($key);
        if ($property === null) {
            return [];
        }

        try {
            $rules = [];
            $isNumericType = $inspector->getTypeSpecForKey($key)?->isNumeric() ?? false;
            $hasNumericConstraint = false;

            foreach ($property->getAttributes(Min::class) as $attribute) {
                /** @var Min $min */
                $min = $attribute->newInstance();
                $hasNumericConstraint = true;
                $rules[] = 'min:'.$min->value;
            }

            foreach ($property->getAttributes(Max::class) as $attribute) {
                /** @var Max $max */
                $max = $attribute->newInstance();
                $hasNumericConstraint = true;
                $rules[] = 'max:'.$max->value;
            }

            if ($hasNumericConstraint && $isNumericType) {
                array_unshift($rules, 'numeric');
            }

            foreach ($property->getAttributes(StringLength::class) as $attribute) {
                /** @var StringLength $length */
                $length = $attribute->newInstance();
                $rules[] = 'min:'.$length->min;
                $rules[] = 'max:'.$length->max;
            }

            foreach ($property->getAttributes(DateFormat::class) as $attribute) {
                /** @var DateFormat $dateFormat */
                $dateFormat = $attribute->newInstance();
                $rules[] = 'date_format:'.$dateFormat->format;
            }

            return $rules;
        } catch (Throwable $e) {
            throw new InspectionException("Failed to build validation rules from attributes for field '$key'", 0, $e);
        }
    }
}
