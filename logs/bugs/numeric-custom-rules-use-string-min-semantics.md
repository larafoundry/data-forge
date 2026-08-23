# Numeric Custom Rules Use String Min Semantics

## Status

Fixed.

## Summary

Custom Laravel size rules such as `min` on `int` and `float` DTO fields could be interpreted with string-size semantics unless users duplicated the PHP type as a Laravel `numeric` or `integer` rule.

## Actual Behavior

`public readonly int $age` with `rules(): ['age' => 'min:1001']` failed generated numeric values with `validation.min.string`.

## Expected Behavior

The DTO PHP type should inform Laravel size-rule semantics at the boundary.

## Root Cause

`DtoRuleBuilder` used a closure for PHP type validation, but Laravel's `min`/`max` semantics are selected by Laravel validation rules such as `numeric`.

## Fix

`DtoRuleBuilder` now prepends `numeric` for numeric DTO fields when custom rules contain numeric size rules and no explicit size-type rule is already present.

## Regression Coverage

Added a PHPUnit POC regression test for `fromArray()` validation with
`rules(): ['age' => 'min:1001']`.
