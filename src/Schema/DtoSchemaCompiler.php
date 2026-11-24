<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Exceptions\InspectionException;
use DateTimeZone;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

final class DtoSchemaCompiler
{
    /** @var array<class-string,DtoSchema<object>> */
    private static array $cache = [];

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return DtoSchema<T>
     *
     * @throws InspectionException
     */
    public static function compile(string $class): DtoSchema
    {
        if (isset(self::$cache[$class])) {
            /** @var DtoSchema<T> */
            return self::$cache[$class];
        }

        if (! class_exists($class)) {
            throw new InspectionException("Class {$class} not found");
        }

        /** @var ReflectionClass<T> $reflection */
        $reflection = new ReflectionClass($class);
        $fields = [];

        $ctor = $reflection->getConstructor();
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $parameter) {
                $name = $parameter->getName();
                $property = self::instanceProperty($reflection, $name);
                $fields[$name] = self::fieldFrom($reflection, $name, $property, $parameter);
            }
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isPromoted() || $property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            if (! array_key_exists($name, $fields)) {
                $fields[$name] = self::fieldFrom($reflection, $name, $property, null);
            }
        }

        ksort($fields, SORT_STRING);
        self::assertUniqueInputKeys($class, $fields);
        self::assertValidDateFormatAttributes($class, $fields);

        $schema = new DtoSchema(
            $class,
            $fields,
            DtoContract::rules($class),
            DtoContract::messages($class)
        );

        /** @var DtoSchema<object> $schema */
        self::$cache[$class] = $schema;

        /** @var DtoSchema<T> */
        return $schema;
    }

    /**
     * @param  class-string  $class
     * @param  array<string,FieldSpec>  $fields
     *
     * @throws InspectionException
     */
    private static function assertUniqueInputKeys(string $class, array $fields): void
    {
        $inputKeyOwners = [];
        foreach ($fields as $field) {
            $inputKeyOwners[$field->inputKey][] = $field->name;
        }

        foreach ($inputKeyOwners as $inputKey => $fieldNames) {
            if (count($fieldNames) <= 1) {
                continue;
            }

            throw new InspectionException(
                "Multiple fields on $class map to the same input key '$inputKey': ".implode(', ', $fieldNames)
            );
        }
    }

    /**
     * @param  class-string  $class
     * @param  array<string,FieldSpec>  $fields
     *
     * @throws InspectionException
     */
    private static function assertValidDateFormatAttributes(string $class, array $fields): void
    {
        foreach ($fields as $field) {
            if ($field->property === null) {
                continue;
            }

            foreach ($field->property->getAttributes(DateFormat::class) as $attribute) {
                $dateFormat = self::attributeInstance($attribute, $class, $field->name);

                self::assertValidDateFormatTimezone($class, $field->name, $dateFormat);
            }
        }
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function assertValidDateFormatTimezone(string $class, string $name, DateFormat $dateFormat): void
    {
        if ($dateFormat->timezone === null) {
            return;
        }

        try {
            new DateTimeZone($dateFormat->timezone);
        } catch (Exception $e) {
            throw new InspectionException(
                "Invalid timezone '{$dateFormat->timezone}' configured for DateFormat field '$name' on $class",
                0,
                $e
            );
        }
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     */
    private static function fieldFrom(
        ReflectionClass $reflection,
        string $name,
        ?ReflectionProperty $property,
        ?ReflectionParameter $parameter
    ): FieldSpec {
        $type = $property?->getType() ?? $parameter?->getType();

        return new FieldSpec(
            $name,
            self::mappedKey($reflection->getName(), $property, $name),
            new TypeSpec($type),
            ! self::hasDefault($property, $parameter) && ! ($type?->allowsNull() ?? false),
            DocBlockItemType::resolve($reflection->getName(), $name, $type, $property, $parameter),
            $property,
        );
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     */
    private static function instanceProperty(ReflectionClass $reflection, string $name): ?ReflectionProperty
    {
        if (! $reflection->hasProperty($name)) {
            return null;
        }

        $property = $reflection->getProperty($name);
        if ($property->isStatic()) {
            return null;
        }

        return $property;
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function mappedKey(string $class, ?ReflectionProperty $property, string $name): string
    {
        $attributes = $property?->getAttributes(MapKey::class) ?? [];
        if ($attributes === []) {
            return $name;
        }

        return self::attributeInstance($attributes[0], $class, $name)->key;
    }

    /**
     * @template A of object
     *
     * @param  ReflectionAttribute<A>  $attribute
     * @param  class-string  $class
     * @return A
     *
     * @throws InspectionException
     */
    private static function attributeInstance(ReflectionAttribute $attribute, string $class, string $name): object
    {
        try {
            return $attribute->newInstance();
        } catch (Throwable $e) {
            throw new InspectionException(
                "Failed to inspect attribute {$attribute->getName()} for field '$name' on $class",
                0,
                $e
            );
        }
    }

    private static function hasDefault(?ReflectionProperty $property, ?ReflectionParameter $parameter): bool
    {
        if ($parameter !== null) {
            return $parameter->isDefaultValueAvailable();
        }

        return $property?->hasDefaultValue() ?? false;
    }
}
