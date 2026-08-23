# Enum Instance In Rule Raw Error

## Status

Fixed.

## Summary

A backed enum instance passed to a DTO field with a Laravel `in` rule leaked a raw PHP `Error`.

## Actual Behavior

Inputting an enum instance into a field typed as that enum worked without custom rules, but adding `in:required,optional` caused Laravel validation to throw `Object of class ... could not be converted to string`.

## Expected Behavior

Accepted enum instances should validate against their backing value during the raw Laravel validation phase and continue through normal DTO hydration.

## Root Cause

Pre-hydration validation ran custom Laravel rules against raw enum objects before Data Forge's type validation/hydration pass.

## Fix

Pre-hydration validation now normalizes enum instances to their validation scalar only for the Laravel validation input. Hydration still uses the original data.

## Regression Coverage

Added a regression test in `tests/Unit/Validation/ValidationLogicTest.php`.
