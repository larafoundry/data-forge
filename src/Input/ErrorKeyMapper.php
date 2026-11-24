<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoSchema;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use ReflectionNamedType;

/**
 * Translates a validation error bag from internal (canonical) property keys to
 * the external input keys the caller actually provided.
 *
 * The validation core works entirely in canonical property keys. This mapper is
 * the single boundary component that knows how to render those keys back into
 * the caller's namespace, descending through nested DTO and collection paths
 * (e.g. `address.firstName` -> `address.first_name`, `people.0.firstName` ->
 * `members.0.first_name`). Segments with no `#[MapKey]` and list indices pass
 * through unchanged.
 */
final class ErrorKeyMapper
{
    /**
     * @param  DtoSchema<object>  $schema  the root DTO schema
     * @param  array<string,array<int,string>>  $errors  canonical-keyed error bag
     * @return array<string,array<int,string>>
     */
    public static function toInputKeys(DtoSchema $schema, array $errors): array
    {
        $mapped = [];
        foreach ($errors as $key => $messages) {
            $mapped[self::translatePath($schema, (string) $key)] = $messages;
        }

        return $mapped;
    }

    /**
     * @param  DtoSchema<object>  $schema
     */
    private static function translatePath(DtoSchema $schema, string $path): string
    {
        $segments = explode('.', $path);
        $current = $schema;
        $out = [];

        foreach ($segments as $segment) {
            if ($current === null || self::isListIndex($segment)) {
                $out[] = $segment;

                continue;
            }

            $out[] = $current->inputKey($segment);
            $current = self::descend($current, $segment);
        }

        return implode('.', $out);
    }

    /**
     * Resolve the schema for the DTO reached by following a property key,
     * whether it is a nested DTO or a typed collection of DTOs.
     *
     * @param  DtoSchema<object>  $schema
     * @return DtoSchema<object>|null
     */
    private static function descend(DtoSchema $schema, string $propertyName): ?DtoSchema
    {
        $itemClass = $schema->field($propertyName)?->arrayItemClass;
        if ($itemClass !== null) {
            return DtoSchemaCompiler::compile($itemClass);
        }

        $type = $schema->field($propertyName)?->type->reflectionType;
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && DtoClass::isDto($type->getName())) {
            return DtoSchemaCompiler::compile($type->getName());
        }

        return null;
    }

    private static function isListIndex(string $segment): bool
    {
        return $segment !== '' && ctype_digit($segment);
    }
}
