<?php

declare(strict_types=1);

namespace Axiom\DataForge\Input;

use Axiom\DataForge\Schema\DtoInspector;

final class InputMapper
{
    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    public static function normalize(DtoInspector $inspector, array $attributes): array
    {
        $keyMap = $inspector->getKeyMap();

        $normalized = [];
        foreach ($inspector->getAcceptedKeys() as $propertyName) {
            $inputKey = $keyMap[$propertyName] ?? $propertyName;
            if (array_key_exists($inputKey, $attributes)) {
                $normalized[$propertyName] = $attributes[$inputKey];
            }
        }

        return $normalized;
    }
}
