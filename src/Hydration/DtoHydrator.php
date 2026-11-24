<?php

declare(strict_types=1);

namespace Axiom\DataForge\Hydration;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Exceptions\DataForgeException;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoSchema;
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
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     * @param  array<string,array<int,RuleValue>>  $customRules
     * @param  array<string,string>  $customMessages
     * @return array<string,mixed>
     *
     * @throws DataForgeException
     */
    public static function hydrate(
        DtoSchema $schema,
        array $attributes,
        array $customRules = [],
        array $customMessages = []
    ): array {
        foreach ($schema->acceptedKeys() as $name) {
            if (! array_key_exists($name, $attributes)) {
                continue;
            }

            $arrayItemClass = $schema->field($name)?->arrayItemClass;
            if ($arrayItemClass !== null) {
                if ($attributes[$name] === null && ($schema->field($name)?->type->reflectionType?->allowsNull() ?? false)) {
                    continue;
                }

                $items = self::hydrateDtoCollection(
                    $attributes[$name],
                    $arrayItemClass,
                    $name,
                    RuleScope::nestedRules($customRules, $name),
                    RuleScope::nestedMessages($customMessages, $name)
                );

                // The PHPDoc item type is only read for `array` and Collection fields.
                $collectionClass = $schema->field($name)?->type?->namedClass();
                $attributes[$name] = $collectionClass === null ? $items : self::makeCollection($collectionClass, $items, $name);

                continue;
            }

            $type = $schema->field($name)?->type->reflectionType;
            if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if ($attributes[$name] instanceof $typeName) {
                continue;
            }

            if (is_array($attributes[$name]) && DtoClass::isDto($typeName)) {
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
                self::getDateFormatForKey($schema, $name, $customRules)
            );
        }

        return $attributes;
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
            $schema = DtoSchemaCompiler::compile($class);
            $validator = Validator::nested($schema, $attributes)
                ->withRules($schema->rules)
                ->withRules($customRules)
                ->withMessages($schema->messages)
                ->withMessages($customMessages);

            return ContainerHelper::makeInstance($class, $validator->validate());
        } catch (ValidationException $e) {
            throw new ValidationException(self::prefixErrors($e->errors, $path));
        }
    }

    /**
     * @param  class-string  $collectionClass
     * @param  list<object>  $items
     * @return Collection<array-key, mixed>
     *
     * @throws InstantiationException
     */
    private static function makeCollection(string $collectionClass, array $items, string $name): Collection
    {
        try {
            $collection = new $collectionClass($items);
        } catch (Throwable $e) {
            throw new InstantiationException("Failed to create $collectionClass for field '$name'", 0, $e);
        }

        if (! ($collection instanceof Collection) || $collection->all() !== $items) {
            throw new InstantiationException("$collectionClass did not keep the items passed to its constructor for field '$name'");
        }

        return $collection;
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
     * @return list<object>
     *
     * @throws DataForgeException
     */
    private static function hydrateDtoCollection(
        mixed $value,
        string $class,
        string $path,
        array $customRules = [],
        array $customMessages = []
    ): array {
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

        $result = [];
        $errors = [];
        foreach ($value as $index => $item) {
            $itemPath = $path.'.'.$index;

            if ($item instanceof $class) {
                $result[] = $item;

                continue;
            }

            if (! is_array($item)) {
                $errors[$itemPath] = ["The $itemPath field has an invalid type."];

                continue;
            }

            try {
                $result[] = self::hydrateDto(
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

        return $result;
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
     * @param  DtoSchema<object>  $schema
     * @param  array<string,array<int,RuleValue>>  $customRules
     *
     * @throws InspectionException
     */
    private static function getDateFormatForKey(DtoSchema $schema, string $name, array $customRules = []): ?DateFormat
    {
        $property = $schema->field($name)?->property;
        if ($property !== null) {
            $attributes = $property->getAttributes(DateFormat::class);
            if ($attributes !== []) {
                // DtoSchemaCompiler has already instantiated and validated it.
                return $attributes[0]->newInstance();
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
        if (enum_exists($type) && is_a($type, BackedEnum::class, true) && (is_string($value) || is_int($value))) {
            try {
                return $type::tryFrom($value) ?? $value;
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
        $format = self::deterministicDateFormat($dateFormat->format);

        return match ($type) {
            Carbon::class => Carbon::createFromFormat($format, $value, $dateFormat->timezone),
            CarbonImmutable::class => CarbonImmutable::createFromFormat($format, $value, $dateFormat->timezone),
            DateTimeImmutable::class, DateTimeInterface::class => DateTimeImmutable::createFromFormat(
                $format,
                $value,
                $timezone
            ),
            DateTime::class => DateTime::createFromFormat($format, $value, $timezone),
            default => $value
        };
    }

    /**
     * Without a leading `!`, PHP fills fields missing from the format with the
     * current time, so `d/m/Y` would hydrate with the wall-clock time of the
     * request. Reset unparsed fields the same way Laravel's `date_format`
     * rule does. A leading `!` is always safe to add, even when the format
     * already contains `!`, `|`, or an escaped literal of either.
     */
    private static function deterministicDateFormat(string $format): string
    {
        return '!'.$format;
    }
}
