# Numeric Union Size Rules String Semantics

## Status

Fixed.

## Summary

Fields typed as `int|float` used Laravel string-size semantics for `min`, `max`, and related size rules.

## Actual Behavior

`int|float $amount` with `min:100` rejected `1000` as `validation.min.string`, while `max:50` accepted `1000`.

The same issue affected `#[Min]` and `#[Max]` attributes on `int|float` fields.

## Expected Behavior

Numeric union fields should use numeric validation semantics.

## Root Cause

`DtoRuleBuilder` only detected numeric fields when the reflected type was a `ReflectionNamedType`. Numeric unions are `ReflectionUnionType`, so the builder did not prepend Laravel's `numeric` rule.

## Fix

Numeric type detection now supports unions that contain only `int`, `float`, and optional `null`.

## Regression Coverage

Added regression tests in `tests/Unit/Validation/ValidationLogicTest.php`.
