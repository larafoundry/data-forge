<?php

declare(strict_types=1);

namespace Axiom\DataForge\Hydration;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Exceptions\DataForgeException;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use Axiom\DataForge\Validation\RuleScope;
use Axiom\DataForge\Validation\Validator;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Collection;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use Throwable;
use TypeError;

/**
 * Hydrates and casts validated attributes into their declared property shapes:
 * nested DTOs, typed collections, backed enums, and date-like values.
 *
 * Casting deliberately preserves the original value when a cast fails (e.g. an
 * unmatched enum case or an unparseable date) so the validation pass can report
 * the offending value instead of a silently nulled field.
 *
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
final class DtoHydrator
{
    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     * @param  array<string,array<int,RuleValue>>  $customRules
     * @param  array<string,string>  $customMessages
     * @return array<string,mixed>
     *
     * @throws DataForgeException
     */
    public static function hydrate(
        DtoInspector $inspector,
        array $attributes,
        array $customRules = [],
        array $customMessages = []
    ): array {
        foreach ($inspector->getAcceptedKeys() as $name) {
            if (! array_key_exists($name, $attributes)) {
                continue;
            }

            $arrayItemClass = $inspector->getArrayItemClassForKey($name);
            if ($arrayItemClass !== null) {
                $type = $inspector->getTypeForKey($name);
                if (! self::isCollectionType($type)) {
                    throw new ValidationException([
                        $name => ["The $name field must be typed as ".Collection::class.' when using ArrayOf.'],
                    ]);
                }

                if ($attributes[$name] === null && $type instanceof ReflectionNamedType && $type->allowsNull()) {
                    continue;
                }

                $attributes[$name] = self::hydrateDtoCollection(
                    $attributes[$name],
                    $arrayItemClass,
                    $name,
                    RuleScope::nestedRules($customRules, $name),
                    RuleScope::nestedMessages($customMessages, $name)
                );

                continue;
            }

            $type = $inspector->getTypeForKey($name);
            if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if ($attributes[$name] instanceof $typeName) {
                continue;
            }

            if (is_array($attributes[$name]) && self::isDtoClass($typeName)) {
                $attributes[$name] = self::hydrateDto(
                    $attributes[$name],
                    $typeName,
                    $name,
                    RuleScope::nestedRules($customRules, $name),
                    RuleScope::nestedMessages($customMessages, $name)
                );

                continue;
            }

            $attributes[$name] = self::castValue(
                $attributes[$name],
                $typeName,
                $type->allowsNull(),
                self::getDateFormatForKey($inspector, $name, $customRules)
            );
        }

        return $attributes;
    }

    /**
     * @param  class-string  $class
     * @return array<string,string|array<int,RuleValue>>
     *
     * @throws InspectionException
     */
    private static function dtoRules(string $class): array
    {
        return DtoSchemaCompiler::compile($class)->rules;
    }

    /**
     * @param  class-string  $class
     * @return array<string,string>
     *
     * @throws InspectionException
     */
    private static function dtoMessages(string $class): array
    {
        return DtoSchemaCompiler::compile($class)->messages;
    }

    /**
     * @phpstan-assert-if-true class-string $class
     */
    private static function isDtoClass(string $class): bool
    {
        return DtoClass::isDto($class);
    }

    private static function isCollectionType(mixed $type): bool
    {
        if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return false;
        }

        return is_a($type->getName(), Collection::class, true);
    }

    /**
     * @param  array<mixed,mixed>  $value
     * @param  class-string  $class
     * @param  string  $path  canonical path, used to prefix ordinary (canonical) nested errors
     * @param  array<string,array<int,RuleValue>>  $customRules
     * @param  array<string,string>  $customMessages
     *
     * @throws DataForgeException
     */
    private static function hydrateDto(
        array $value,
        string $class,
        string $path,
        array $customRules = [],
        array $customMessages = []
    ): object {
        $attributes = self::stringKeyedAttributes($value, $path);

        try {
            $inspector = new DtoInspector($class);
            $validator = Validator::nested($inspector, $attributes)
                ->withRules(self::dtoRules($class))
                ->withRules($customRules)
                ->withMessages(self::dtoMessages($class))
                ->withMessages($customMessages);

            $dto = ContainerHelper::makeInstance($class, $validator->validate());
        } catch (ValidationException $e) {
            throw new ValidationException(self::prefixErrors($e->errors, $path));
        }

        if (! is_object($dto)) {
            throw new ValidationException([
                $path => ["The $path field has an invalid type."],
            ]);
        }

        return $dto;
    }

    /**
     * @param  array<mixed,mixed>  $value
     * @return array<string,mixed>
     *
     * @throws ValidationException
     */
    private static function stringKeyedAttributes(array $value, string $path): array
    {
        $attributes = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new ValidationException([
                    $path => ["The $path field must be a DTO record."],
                ]);
            }

            $attributes[$key] = $item;
        }

        return $attributes;
    }

    /**
     * @param  class-string  $class
     * @param  string  $path  canonical path, used to prefix ordinary (canonical) nested errors
     * @param  array<string,array<int,RuleValue>>  $customRules
     * @param  array<string,string>  $customMessages
     * @return Collection<array-key, object>
     *
     * @throws DataForgeException
     */
    private static function hydrateDtoCollection(
        mixed $value,
        string $class,
        string $path,
        array $customRules = [],
        array $customMessages = []
    ): Collection {
        if (! is_array($value)) {
            throw new ValidationException([
                $path => ["The $path field must be an array."],
            ]);
        }

        if (! array_is_list($value)) {
            throw new ValidationException([
                $path => ["The $path field must be a list of DTO records."],
            ]);
        }

        if (! self::isDtoClass($class)) {
            throw new ValidationException([
                $path => ["The $path field has an invalid DTO type."],
            ]);
        }

        $result = [];
        $errors = [];
        foreach ($value as $index => $item) {
            $itemPath = $path.'.'.$index;

            if ($item instanceof $class) {
                $result[$index] = $item;

                continue;
            }

            if (! is_array($item)) {
                $errors[$itemPath] = ["The $itemPath field has an invalid type."];

                continue;
            }

            try {
                $result[$index] = self::hydrateDto(
                    $item,
                    $class,
                    $itemPath,
                    RuleScope::collectionItemRules($customRules, $index),
                    RuleScope::collectionItemMessages($customMessages, $index)
                );
            } catch (ValidationException $e) {
                self::mergeErrors($errors, $e->errors);
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new Collection($result);
    }

    /**
     * @param  array<string,array<int,string>>  $errors
     * @return array<string,array<int,string>>
     */
    private static function prefixErrors(array $errors, string $prefix): array
    {
        $prefixed = [];
        foreach ($errors as $key => $messages) {
            $prefixed[$prefix.'.'.$key] = $messages;
        }

        return $prefixed;
    }

    /**
     * @param  array<string,array<int,string>>  $target
     * @param  array<string,array<int,string>>  $source
     */
    private static function mergeErrors(array &$target, array $source): void
    {
        foreach ($source as $key => $messages) {
            $target[$key] = $messages;
        }
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,array<int,RuleValue>>  $customRules
     *
     * @throws InspectionException
     */
    private static function getDateFormatForKey(DtoInspector $inspector, string $name, array $customRules = []): ?DateFormat
    {
        $property = $inspector->getReflectionProperty($name);
        if ($property !== null) {
            $attributes = $property->getAttributes(DateFormat::class);
            if ($attributes !== []) {
                try {
                    /** @var DateFormat $dateFormat */
                    $dateFormat = $attributes[0]->newInstance();
                } catch (Throwable $e) {
                    throw new InspectionException("Failed to inspect date format attribute for field '$name'", 0, $e);
                }

                return $dateFormat;
            }
        }

        foreach ($customRules[$name] ?? [] as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $parts = explode(':', $rule, 2);
            if (mb_strtolower($parts[0]) === 'date_format' && isset($parts[1]) && $parts[1] !== '') {
                return new DateFormat($parts[1]);
            }
        }

        return null;
    }

    private static function castValue(
        mixed $value,
        string $type,
        bool $allowsNull = false,
        ?DateFormat $dateFormat = null
    ): mixed {
        // Handle backed enums
        if (enum_exists($type)) {
            try {
                $reflectionEnum = new ReflectionEnum($type);
                if ($reflectionEnum->isBacked() && (is_string($value) || is_int($value))) {
                    /** @var class-string<BackedEnum> $type */
                    return $type::tryFrom($value) ?? $value;
                }
            } catch (ReflectionException $e) {
                throw new InspectionException("Failed to cast value for enum '$type'", 0, $e);
            } catch (TypeError) {
                return $value;
            }
        }

        // Handle datetime types
        if (is_string($value)) {
            if (self::isEmptyString($value)) {
                return $allowsNull && self::isDateLikeType($type) ? null : $value;
            }

            try {
                if ($dateFormat !== null) {
                    return self::castFormattedDateValue($value, $type, $dateFormat);
                }

                return self::castDateValue($value, $type);
            } catch (Throwable $e) {
                return $value;
            }
        }

        return $value;
    }

    private static function isEmptyString(string $value): bool
    {
        return preg_match('/\S/', $value) !== 1;
    }

    private static function isDateLikeType(string $type): bool
    {
        return in_array($type, [
            Carbon::class,
            CarbonImmutable::class,
            DateTime::class,
            DateTimeImmutable::class,
            DateTimeInterface::class,
        ], true);
    }

    private static function castDateValue(string $value, string $type): mixed
    {
        return match ($type) {
            Carbon::class => Carbon::parse($value),
            CarbonImmutable::class => CarbonImmutable::parse($value),
            DateTimeImmutable::class, DateTimeInterface::class => new DateTimeImmutable($value),
            DateTime::class => new DateTime($value),
            default => $value
        };
    }

    private static function castFormattedDateValue(string $value, string $type, DateFormat $dateFormat): mixed
    {
        $timezone = $dateFormat->timezone !== null ? new DateTimeZone($dateFormat->timezone) : null;

        return match ($type) {
            Carbon::class => Carbon::createFromFormat($dateFormat->format, $value, $dateFormat->timezone),
            CarbonImmutable::class => CarbonImmutable::createFromFormat($dateFormat->format, $value, $dateFormat->timezone),
            DateTimeImmutable::class, DateTimeInterface::class => DateTimeImmutable::createFromFormat(
                $dateFormat->format,
                $value,
                $timezone
            ),
            DateTime::class => DateTime::createFromFormat($dateFormat->format, $value, $timezone),
            default => $value
        };
    }
}
