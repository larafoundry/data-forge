# Numeric Attributes Use String Min Semantics

## Status

Fixed.

## Summary

Numeric `#[Min]` and `#[Max]` attributes generated Laravel `min` and `max` rules without marking numeric fields as numeric.

## Actual Behavior

Laravel interpreted `min:1001` for an integer field as a string length constraint and failed with `validation.min.string`.

## Expected Behavior

Numeric attributes on `int` and `float` DTO fields should use numeric validation semantics.

## Root Cause

`DtoRuleBuilder` emitted `min`/`max` rules from numeric attributes without adding `numeric` for numeric PHP types.

## Fix

`DtoRuleBuilder` now prepends `numeric` when numeric constraints are declared on `int` or `float` fields.

## Regression Coverage

Covered by validation tests that exercise large numeric minimum constraints.
