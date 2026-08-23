# Nested Numeric Dotted Rules Use String Semantics

## Status

Fixed.

## Summary

Custom dotted rules such as `child.age => min:100` did not inspect the nested DTO field type. Laravel could apply string-size semantics instead of numeric semantics for nested `int` and `float` fields.

## Reproduction

`tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`

- `test_nested_numeric_min_rule_uses_nested_property_numeric_semantics`
- `test_nested_numeric_max_rule_rejects_values_above_max`

## Root Cause

`DtoRuleBuilder` only checked the root field type when deciding whether to prepend `numeric` for custom size rules.

## Fix

`DtoRuleBuilder` now resolves dotted rule paths into nested DTO and `ArrayOf` item inspectors before applying numeric size-rule semantics.

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`
- `composer phpstan`
