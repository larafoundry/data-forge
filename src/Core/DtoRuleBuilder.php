<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Closure;
use InvalidArgumentException;

final class DtoRuleBuilder
{
    /**
     * Gộp rule hệ thống (required, nullable, type-check) với rule tuỳ chỉnh.
     *
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,string[]>  $customRules  Đã chuẩn hoá bởi withRules()
     * @param  array<string,mixed>  $attributes  Payload gốc
     * @return array<string, array<string|Closure>>
     */
    public static function build(
        DtoInspector $inspector,
        array $customRules,
        array $attributes
    ): array {
        $rules = [];
        $keyMap = $inspector->getKeyMap();
        $reverseKeyMap = array_flip($keyMap);

        /* ---- required -------------------------------------------------- */
        foreach ($inspector->getRequiredKeys() as $key) {
            $mappedKey = $keyMap[$key] ?? $key;
            $rules[$key][] = 'required';
        }

        /* ---- nullable + type check ------------------------------------- */
        foreach ($inspector->getAcceptedKeys() as $key) {
            // nullable nếu property cho phép null và không nằm trong required
            if (! in_array($key, $inspector->getRequiredKeys(), true)
                && $inspector->isTypeAcceptedForKey($key, null)
            ) {
                $rules[$key][] = 'nullable';
            }

            // closure kiểm tra đúng kiểu
            $rules[$key][] = static function (string $attribute, mixed $value, Closure $fail) use ($inspector, $reverseKeyMap) {
                // Map the attribute back to the property name if needed
                $propertyName = $reverseKeyMap[$attribute] ?? $attribute;

                // chỉ check khi attribute thực sự được gửi lên
                if (array_key_exists($propertyName, $inspector->getReflection()->getDefaultProperties()) && $value === null) {
                    return;
                }
                if (! $inspector->isTypeAcceptedForKey($propertyName, $value)) {
                    $fail("The $attribute field has an invalid type.");
                }
            };
        }

        /* ---- merge custom rules ---------------------------------------- */
        foreach ($customRules as $field => $fieldRules) {
            $rules[$field] = array_merge($rules[$field] ?? [], $fieldRules);
        }

        /* ---- các key "lạ" trong payload → bỏ qua ----------------------- */
        foreach (array_keys($attributes) as $key) {
            $propertyName = $reverseKeyMap[$key] ?? $key;
            if (! isset($rules[$propertyName])) {
                $rules[$propertyName][] = 'nullable';
            }
        }

        return $rules;
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Chuẩn hoá rule về **mảng phẳng string**.
     *
     * @param  string|array<mixed>  $raw
     * @return array<string>
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeFieldRules(string|array $raw): array
    {
        // pipe-string: 'required|min:3'
        if (is_string($raw)) {
            return array_map('trim', explode('|', $raw));
        }

        // mảng phẳng string
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
