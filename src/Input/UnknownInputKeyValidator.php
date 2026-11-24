<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoSchema;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use ReflectionNamedType;

final class UnknownInputKeyValidator
{
    /**
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     *
     * @throws InspectionException
     * @throws UnknownInputKeyException
     */
    public static function assertNoUnknownKeys(
        DtoSchema $schema,
        array $attributes,
        bool $rejectsUnknownInputKeys
    ): void {
        $errors = self::collect($schema, $attributes, '', $rejectsUnknownInputKeys);

        if ($errors !== []) {
            throw new UnknownInputKeyException($errors);
        }
    }

    /**
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     * @return array<string,array<int,string>>
     *
     * @throws InspectionException
     */
    private static function collect(
        DtoSchema $schema,
        array $attributes,
        string $path,
        bool $rejectsUnknownInputKeys
    ): array {
        $errors = $rejectsUnknownInputKeys
            ? self::unknownKeyErrors($schema, $attributes, $path)
            : [];

        foreach ($schema->acceptedKeys() as $propertyName) {
            $inputKey = $schema->inputKey($propertyName);
            if (! array_key_exists($inputKey, $attributes)) {
                continue;
            }

            $value = $attributes[$inputKey];
            $fieldPath = self::joinPath($path, $inputKey);
            $itemClass = $schema->field($propertyName)?->arrayItemClass;

            if ($itemClass !== null) {
                self::mergeErrors($errors, self::collectCollection($value, $itemClass, $fieldPath));

                continue;
            }

            $nestedClass = self::nestedDtoClass($schema, $propertyName);
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
                    DtoSchemaCompiler::compile($nestedClass),
                    $nestedAttributes,
                    $fieldPath,
                    DtoClass::rejectsUnknownInputKeys($nestedClass)
                )
            );
        }

        return $errors;
    }

    /**
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     * @return array<string,array<int,string>>
     */
    private static function unknownKeyErrors(DtoSchema $schema, array $attributes, string $path): array
    {
        $acceptedInputKeys = [];
        foreach ($schema->acceptedKeys() as $propertyName) {
            $acceptedInputKeys[$schema->inputKey($propertyName)] = true;
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
        $itemSchema = DtoSchemaCompiler::compile($class);
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
                    $itemSchema,
                    $attributes,
                    self::joinPath($path, (string) $index),
                    $rejectsUnknownInputKeys
                )
            );
        }

        return $errors;
    }

    /**
     * @param  DtoSchema<object>  $schema
     * @return class-string|null
     */
    private static function nestedDtoClass(DtoSchema $schema, string $propertyName): ?string
    {
        $type = $schema->field($propertyName)?->type->reflectionType;
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
