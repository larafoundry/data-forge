<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Input\InputMapper;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoInspector;
use BackedEnum;
use Illuminate\Support\Collection;
use ReflectionNamedType;
use UnitEnum;

final class ValidationProjector
{
    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     *
     * @throws InspectionException
     */
    public static function project(DtoInspector $inspector, array $attributes, string $pathPrefix = ''): array
    {
        $projected = [];

        foreach ($attributes as $field => $value) {
            $path = self::joinPath($pathPrefix, $field);
            $projected[$field] = self::projectField($inspector, $field, $value, $path);
        }

        return $projected;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     *
     * @throws InspectionException
     */
    private static function projectField(DtoInspector $inspector, string $field, mixed $value, string $path): mixed
    {
        $arrayItemClass = $inspector->getArrayItemClassForKey($field);
        if ($arrayItemClass !== null) {
            return self::projectCollectionValue($value, $arrayItemClass, $path);
        }

        $type = $inspector->getTypeForKey($field);
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();
            if (DtoClass::isDto($typeName)) {
                return self::projectDtoValue($value, $typeName, $path);
            }
        }

        return self::projectValue($value);
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function projectCollectionValue(mixed $value, string $class, string $path): mixed
    {
        if ($value instanceof Collection) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::projectCollectionItem($item, $class, self::joinPath($path, (string) $key));
            }

            return $items;
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::projectCollectionItem($item, $class, self::joinPath($path, (string) $key));
            }

            return $items;
        }

        return self::projectValue($value);
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function projectCollectionItem(mixed $item, string $class, string $path): mixed
    {
        if ($item instanceof $class) {
            return self::projectDtoObject($item, new DtoInspector($class), $path);
        }

        if (is_array($item)) {
            return self::projectDtoArray($item, $class, $path);
        }

        return self::projectValue($item);
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function projectDtoValue(mixed $value, string $class, string $path): mixed
    {
        if ($value instanceof $class) {
            return self::projectDtoObject($value, new DtoInspector($class), $path);
        }

        if (is_array($value)) {
            return self::projectDtoArray($value, $class, $path);
        }

        return self::projectValue($value);
    }

    /**
     * @param  array<mixed,mixed>  $value
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function projectDtoArray(array $value, string $class, string $path): mixed
    {
        $attributes = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return self::projectValue($value);
            }

            $attributes[$key] = $item;
        }

        $inspector = new DtoInspector($class);

        try {
            $normalized = InputMapper::normalize($inspector, $attributes);
        } catch (ValidationException $e) {
            throw new ValidationException(self::prefixErrors($e->errors, $path));
        }

        return self::project($inspector, $normalized, $path);
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @return array<string,mixed>
     *
     * @throws InspectionException
     */
    private static function projectDtoObject(object $value, DtoInspector $inspector, string $path): array
    {
        $attributes = [];

        foreach ($inspector->getAcceptedKeys() as $field) {
            $property = $inspector->getReflectionProperty($field);
            if ($property === null || ! $property->isInitialized($value)) {
                continue;
            }

            $attributes[$field] = $property->getValue($value);
        }

        return self::project($inspector, $attributes, $path);
    }

    /**
     * @throws InspectionException
     */
    private static function projectValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Collection) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::projectValue($item);
            }

            return $items;
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::projectValue($item);
            }

            return $items;
        }

        return $value;
    }

    private static function joinPath(string $prefix, string $field): string
    {
        return $prefix === '' ? $field : $prefix.'.'.$field;
    }

    /**
     * @param  array<string,array<int,string>>  $errors
     * @return array<string,array<int,string>>
     */
    private static function prefixErrors(array $errors, string $prefix): array
    {
        if ($prefix === '') {
            return $errors;
        }

        $prefixed = [];
        foreach ($errors as $key => $messages) {
            $prefixed[$prefix.'.'.$key] = $messages;
        }

        return $prefixed;
    }
}
