<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Input\InputMapper;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoSchema;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use BackedEnum;
use Illuminate\Support\Collection;
use ReflectionNamedType;
use UnitEnum;

/**
 * Builds the canonical view of input that pre-hydration validation rules run
 * against: nested DTO arrays and objects become canonical arrays, and enums
 * become their values.
 */
final class ValidationProjector
{
    /**
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     *
     * @throws InspectionException
     */
    public static function project(DtoSchema $schema, array $attributes): array
    {
        $projected = [];

        foreach ($attributes as $field => $value) {
            $projected[$field] = self::projectField($schema, $field, $value);
        }

        return $projected;
    }

    /**
     * @param  DtoSchema<object>  $schema
     *
     * @throws InspectionException
     */
    private static function projectField(DtoSchema $schema, string $field, mixed $value): mixed
    {
        $arrayItemClass = $schema->field($field)?->arrayItemClass;
        if ($arrayItemClass !== null) {
            if (! ($value instanceof Collection) && ! is_array($value)) {
                return self::projectValue($value);
            }

            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::projectDtoValue($item, $arrayItemClass);
            }

            return $items;
        }

        $type = $schema->field($field)?->type->reflectionType;
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && DtoClass::isDto($type->getName())) {
            return self::projectDtoValue($value, $type->getName());
        }

        return self::projectValue($value);
    }

    /**
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function projectDtoValue(mixed $value, string $class): mixed
    {
        if ($value instanceof $class) {
            return self::projectDtoObject($value, DtoSchemaCompiler::compile($class));
        }

        if (is_array($value)) {
            return self::projectDtoArray($value, $class);
        }

        return self::projectValue($value);
    }

    /**
     * @param  array<mixed,mixed>  $value
     * @param  class-string  $class
     *
     * @throws InspectionException
     */
    private static function projectDtoArray(array $value, string $class): mixed
    {
        $attributes = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return self::projectValue($value);
            }

            $attributes[$key] = $item;
        }

        $schema = DtoSchemaCompiler::compile($class);

        return self::project($schema, InputMapper::normalize($schema, $attributes));
    }

    /**
     * @param  DtoSchema<object>  $schema
     * @return array<string,mixed>
     *
     * @throws InspectionException
     */
    private static function projectDtoObject(object $value, DtoSchema $schema): array
    {
        $attributes = [];

        foreach ($schema->fields as $field => $spec) {
            $property = $spec->property;
            if ($property === null || ! $property->isInitialized($value)) {
                continue;
            }

            $attributes[$field] = $property->getValue($value);
        }

        return self::project($schema, $attributes);
    }

    private static function projectValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Collection || is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::projectValue($item);
            }

            return $items;
        }

        return $value;
    }
}
