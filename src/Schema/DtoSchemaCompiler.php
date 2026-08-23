<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Exceptions\InspectionException;
use DateTimeZone;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
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
            $reflection,
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
                try {
                    /** @var DateFormat $dateFormat */
                    $dateFormat = $attribute->newInstance();
                } catch (Throwable $e) {
                    throw new InspectionException(
                        "Failed to inspect date format attribute for field '{$field->name}' on $class",
                        0,
                        $e
                    );
                }

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
        $hasDefault = self::hasDefault($property, $parameter);
        $defaultValue = $hasDefault ? self::defaultValue($property, $parameter) : null;
        $nullable = self::typeAllowsNull($type);

        return new FieldSpec(
            $name,
            self::mappedKey($property, $name),
            new TypeSpec($type),
            ! $hasDefault && ! $nullable,
            $nullable,
            $hasDefault,
            $defaultValue,
            self::arrayItemClass($property, $parameter),
            $property,
            $parameter,
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

    private static function mappedKey(?ReflectionProperty $property, string $name): string
    {
        if ($property === null) {
            return $name;
        }

        $attributes = $property->getAttributes(MapKey::class);
        if ($attributes === []) {
            return $name;
        }

        /** @var MapKey $mapKey */
        $mapKey = $attributes[0]->newInstance();

        return $mapKey->key;
    }

    /**
     * @return class-string|null
     *
     * @throws InspectionException
     */
    private static function arrayItemClass(?ReflectionProperty $property, ?ReflectionParameter $parameter): ?string
    {
        $attributes = $property?->getAttributes(ArrayOf::class) ?? [];
        if ($attributes === [] && $parameter !== null) {
            $attributes = $parameter->getAttributes(ArrayOf::class);
        }

        if ($attributes === []) {
            return null;
        }

        /** @var ArrayOf $arrayOf */
        $arrayOf = $attributes[0]->newInstance();

        if (! class_exists($arrayOf->class)) {
            throw new InspectionException("Array item class '{$arrayOf->class}' does not exist");
        }

        return $arrayOf->class;
    }

    private static function hasDefault(?ReflectionProperty $property, ?ReflectionParameter $parameter): bool
    {
        if ($parameter !== null) {
            return $parameter->isDefaultValueAvailable();
        }

        return $property?->hasDefaultValue() ?? false;
    }

    private static function defaultValue(?ReflectionProperty $property, ?ReflectionParameter $parameter): mixed
    {
        if ($parameter !== null && $parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($property !== null && $property->hasDefaultValue()) {
            return $property->getDefaultValue();
        }

        return null;
    }

    private static function typeAllowsNull(?ReflectionType $type): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type->allowsNull()) {
            return true;
        }

        if (! ($type instanceof ReflectionNamedType)) {
            return false;
        }

        return $type->getName() === 'null';
    }
}
