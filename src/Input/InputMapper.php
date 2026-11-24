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
        $normalized = [];
        foreach ($schema->fields as $propertyName => $field) {
            if (array_key_exists($field->inputKey, $attributes)) {
                $normalized[$propertyName] = $attributes[$field->inputKey];
            }
        }

        return $normalized;
    }
}
