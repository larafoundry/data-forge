<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Ws\DataBridge\Rules\DtoType;

final class DtoRuleBuilder
{
    /**
     * Trả về mảng rule Laravel hoàn chỉnh
     *
     * @param  array<string, string|array>  $extraRules  // rules() từ DTO hoặc ngoài vào
     * @return array<string, array|string>
     */
    public static function build(DtoInspector $inspector, array $extraRules): array
    {
        $rules = [];

        // 1. Field bắt buộc (từ constructor + public prop không default)
        foreach ($inspector->getRequiredKeys() as $key) {
            $rules[$key][] = 'required';
        }

        // 2. Merge thêm rules tuỳ chỉnh do người dùng cung cấp
        foreach ($extraRules as $field => $fieldRules) {
            $rules[$field] = array_merge($rules[$field] ?? [], is_string($fieldRules)
                ? explode('|', $fieldRules)
                : $fieldRules);
        }

        // 3. Kiểm tra kiểu dữ liệu của từng property
        foreach ($inspector->getAcceptedKeys() as $key) {
            $prop = $inspector->getReflection()->getProperty($key);
            $rules[$key][] = new DtoType($prop);
        }

        return $rules;
    }
}
