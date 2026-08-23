<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoInspector;
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
     * @param  DtoInspector<object>  $inspector  the root DTO inspector
     * @param  array<string,array<int,string>>  $errors  canonical-keyed error bag
     * @return array<string,array<int,string>>
     */
    public static function toInputKeys(DtoInspector $inspector, array $errors): array
    {
        $mapped = [];
        foreach ($errors as $key => $messages) {
            $mapped[self::translatePath($inspector, (string) $key)] = $messages;
        }

        return $mapped;
    }

    /**
     * @param  DtoInspector<object>  $inspector
     */
    private static function translatePath(DtoInspector $inspector, string $path): string
    {
        $segments = explode('.', $path);
        $current = $inspector;
        $out = [];

        foreach ($segments as $segment) {
            if ($current === null || self::isListIndex($segment)) {
                $out[] = $segment;

                continue;
            }

            $out[] = $current->getMappedKey($segment);
            $current = self::descend($current, $segment);
        }

        return implode('.', $out);
    }

    /**
     * Resolve the inspector for the DTO reached by following a property key,
     * whether it is a nested DTO or a typed collection of DTOs.
     *
     * @param  DtoInspector<object>  $inspector
     * @return DtoInspector<object>|null
     */
    private static function descend(DtoInspector $inspector, string $propertyName): ?DtoInspector
    {
        $itemClass = $inspector->getArrayItemClassForKey($propertyName);
        if ($itemClass !== null) {
            return new DtoInspector($itemClass);
        }

        $type = $inspector->getTypeForKey($propertyName);
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && DtoClass::isDto($type->getName())) {
            return new DtoInspector($type->getName());
        }

        return null;
    }

    private static function isListIndex(string $segment): bool
    {
        return $segment !== '' && ctype_digit($segment);
    }
}
