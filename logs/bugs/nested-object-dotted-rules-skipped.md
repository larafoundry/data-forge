# Nested Object Dotted Rules Skipped

## Status

Fixed.

## Summary

Parent DTO dotted validation rules were skipped when the nested value was already an instance of the nested DTO class.

## Reproduction

`tests/Unit/Validation/NestedObjectDottedRulesTest.php`

- `test_parent_dotted_rule_validates_already_hydrated_nested_dto_instance`

The parent DTO declares `child.name => required|min:3` and receives `child` as an already-hydrated DTO instance with `name = "Al"`.

## Expected Behavior

The parent dotted rule should still be enforced and report the error at `child.name`.

## Root Cause

`Validator::autoCastAttributes()` short-circuited on `instanceof $typeName` before applying nested rules forwarded from the parent DTO.

## Fix

Already-hydrated nested DTO instances are now projected into validation data and checked against forwarded parent dotted rules before being accepted.

## Verification

- `vendor/bin/phpunit tests/Unit/Validation/NestedObjectDottedRulesTest.php`
