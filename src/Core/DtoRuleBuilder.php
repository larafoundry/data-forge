<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Closure;
use InvalidArgumentException;
use ReflectionException;

final class DtoRuleBuilder
{
    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,string[]>  $customRules
     * @param  array<string,mixed>  $attributes
     * @return array<string, array<string|Closure>>
     *
     * @throws ReflectionException
     */
    public static function build(
        DtoInspector $inspector,
        array $customRules,
        array $attributes
    ): array {
        $rules = [];
        $keyMap = $inspector->getKeyMap();
        $reverseKeyMap = array_flip($keyMap);

        foreach ($inspector->getRequiredKeys() as $key) {
            $rules[$key][] = 'required';
        }

        foreach ($inspector->getAcceptedKeys() as $key) {
            if (! in_array($key, $inspector->getRequiredKeys(), true)
                && $inspector->isTypeAcceptedForKey($key, null)
            ) {
                $rules[$key][] = 'nullable';
            }

            $rules[$key][] = static function (string $attribute, mixed $value, Closure $fail) use ($inspector, $reverseKeyMap) {
                $propertyName = $reverseKeyMap[$attribute] ?? $attribute;

                if (array_key_exists($propertyName, $inspector->getReflection()->getDefaultProperties()) && $value === null) {
                    return;
                }
                if (! $inspector->isTypeAcceptedForKey($propertyName, $value)) {
                    $fail("The $attribute field has an invalid type.");
                }
            };
        }

        $acceptedKeys = $inspector->getAcceptedKeys();

        foreach ($customRules as $field => $fieldRules) {
            // Check if the field exists in the DTO before adding rules for it
            if (! in_array($field, $acceptedKeys, true)) {
                throw new InvalidArgumentException(
                    "Cannot add validation rules for field '$field' because it does not exist in the DTO."
                );
            }

            $rules[$field] = array_merge($rules[$field] ?? [], $fieldRules);
        }

        foreach (array_keys($attributes) as $key) {
            $propertyName = $reverseKeyMap[$key] ?? $key;
            if (! isset($rules[$propertyName])) {
                $rules[$propertyName][] = 'nullable';
            }
        }

        return $rules;
    }

    /**
     * @param  string|array<int, string>  $raw
     * @return array<string>
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeFieldRules(string|array $raw): array
    {
        if (is_string($raw)) {
            return array_map('trim', explode('|', $raw));
        }

        $result = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException(
                    'Validation rules must be pipe-string or flat string array (e.g. ["required", "min:3"]).'
                );
            }
            $result[] = $item;
        }

        return $result;
    }
}
