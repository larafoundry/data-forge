<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

/**
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
final class RulePlanner
{
    private const RAW_SHAPE_RULES = [
        'array',
        'list',
        'required_array_keys',
    ];

    /**
     * @param  array<string,array<int,RuleValue>>  $rules
     * @return array<string,array<int,string>>
     */
    public static function rawShapeRules(array $rules): array
    {
        $rawShapeRules = [];

        foreach ($rules as $field => $fieldRules) {
            if (str_contains($field, '.')) {
                continue;
            }

            $shapeRules = [];
            foreach ($fieldRules as $rule) {
                if (! is_string($rule)) {
                    continue;
                }

                if (self::isRawShapeRule($rule)) {
                    $shapeRules[] = $rule;
                }
            }

            if ($shapeRules !== []) {
                $rawShapeRules[$field] = $shapeRules;
            }
        }

        return $rawShapeRules;
    }

    private static function isRawShapeRule(string $rule): bool
    {
        return in_array(self::ruleName($rule), self::RAW_SHAPE_RULES, true);
    }

    private static function ruleName(string $rule): string
    {
        return mb_strtolower(explode(':', $rule, 2)[0]);
    }
}
