# Promoted Constructor Default Required

## Status

Fixed.

## Summary

Promoted constructor parameters with default values were treated as required DTO input.

## Actual Behavior

`public function __construct(public readonly string $name = 'guest')` still required `name` in input.

## Expected Behavior

Promoted constructor defaults should behave like constructor defaults and be used when input is missing.

## Root Cause

`DtoInspector` inspected promoted constructor parameters once as parameters and again as public properties. PHP reflection does not expose promoted constructor defaults through `ReflectionProperty::hasDefaultValue()`, so the second pass marked the field as required.

## Fix

`DtoInspector` now skips promoted properties in public property required/optional detection because they are already represented by constructor parameters.

## Regression Coverage

Added a PHPUnit POC regression test for an omitted promoted constructor default.
