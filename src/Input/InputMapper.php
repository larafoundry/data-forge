<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

use Axiom\DataForge\Schema\DtoSchema;

final class InputMapper
{
    /**
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    public static function normalize(DtoSchema $schema, array $attributes): array
    {
        $keyMap = $schema->keyMap();

        $normalized = [];
        foreach ($schema->acceptedKeys() as $propertyName) {
            $inputKey = $keyMap[$propertyName] ?? $propertyName;
            if (array_key_exists($inputKey, $attributes)) {
                $normalized[$propertyName] = $attributes[$inputKey];
            }
        }

        return $normalized;
    }
}
