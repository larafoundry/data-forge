<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoInspector;
use ReflectionNamedType;

final class UnknownInputKeyValidator
{
    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     *
     * @throws InspectionException
     * @throws UnknownInputKeyException
     */
    public static function assertNoUnknownKeys(
        DtoInspector $inspector,
        array $attributes,
        bool $rejectsUnknownInputKeys
    ): void {
        $errors = self::collect($inspector, $attributes, '', $rejectsUnknownInputKeys);

        if ($errors !== []) {
            throw new UnknownInputKeyException($errors);
        }
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     * @return array<string,array<int,string>>
     *
     * @throws InspectionException
     */
    private static function collect(
        DtoInspector $inspector,
        array $attributes,
        string $path,
        bool $rejectsUnknownInputKeys
    ): array {
        $errors = $rejectsUnknownInputKeys
            ? self::unknownKeyErrors($inspector, $attributes, $path)
            : [];

        foreach ($inspector->getAcceptedKeys() as $propertyName) {
            $inputKey = $inspector->getMappedKey($propertyName);
            if (! array_key_exists($inputKey, $attributes)) {
                continue;
            }

            $value = $attributes[$inputKey];
            $fieldPath = self::joinPath($path, $inputKey);
            $itemClass = $inspector->getArrayItemClassForKey($propertyName);

            if ($itemClass !== null) {
                self::mergeErrors($errors, self::collectCollection($value, $itemClass, $fieldPath));

                continue;
            }

            $nestedClass = self::nestedDtoClass($inspector, $propertyName);
            if ($nestedClass === null || ! is_array($value)) {
                continue;
            }

            $nestedAttributes = self::stringKeyedAttributes($value);
            if ($nestedAttributes === null) {
                continue;
            }

            self::mergeErrors(
                $errors,
                self::collect(
                    new DtoInspector($nestedClass),
                    $nestedAttributes,
                    $fieldPath,
                    DtoClass::rejectsUnknownInputKeys($nestedClass)
                )
            );
        }

        return $errors;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     * @return array<string,array<int,string>>
     */
    private static function unknownKeyErrors(DtoInspector $inspector, array $attributes, string $path): array
    {
        $acceptedInputKeys = [];
        foreach ($inspector->getAcceptedKeys() as $propertyName) {
            $acceptedInputKeys[$inspector->getMappedKey($propertyName)] = true;
        }

        $errors = [];
        foreach (array_keys($attributes) as $key) {
            if (array_key_exists($key, $acceptedInputKeys)) {
                continue;
            }

            $inputKey = (string) $key;
            $errors[self::joinPath($path, $inputKey)] = ["The $inputKey field is not allowed."];
        }

        return $errors;
    }

    /**
     * @param  class-string  $class
     * @return array<string,array<int,string>>
     *
     * @throws InspectionException
     */
    private static function collectCollection(mixed $value, string $class, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value) || ! DtoClass::isDto($class)) {
            return [];
        }

        $errors = [];
        $itemInspector = new DtoInspector($class);
        $rejectsUnknownInputKeys = DtoClass::rejectsUnknownInputKeys($class);

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $attributes = self::stringKeyedAttributes($item);
            if ($attributes === null) {
                continue;
            }

            self::mergeErrors(
                $errors,
                self::collect(
                    $itemInspector,
                    $attributes,
                    self::joinPath($path, (string) $index),
                    $rejectsUnknownInputKeys
                )
            );
        }

        return $errors;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @return class-string|null
     */
    private static function nestedDtoClass(DtoInspector $inspector, string $propertyName): ?string
    {
        $type = $inspector->getTypeForKey($propertyName);
        if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();

        return DtoClass::isDto($class) ? $class : null;
    }

    /**
     * @param  array<mixed,mixed>  $value
     * @return array<string,mixed>|null
     */
    private static function stringKeyedAttributes(array $value): ?array
    {
        $attributes = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return null;
            }

            $attributes[$key] = $item;
        }

        return $attributes;
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

    private static function joinPath(string $prefix, string $field): string
    {
        return $prefix === '' ? $field : $prefix.'.'.$field;
    }
}
