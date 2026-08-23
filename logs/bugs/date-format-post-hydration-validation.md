# Date Format Post Hydration Validation

## Status

Fixed.

## Summary

`#[DateFormat]` rules were applied again after date strings had already been hydrated into date objects.

## Actual Behavior

A valid raw date string such as `2024-01-02` failed with `validation.date_format` after it was cast to `Carbon`.

## Expected Behavior

Format constraints should validate boundary input before hydration. Post-hydration validation should enforce the resulting PHP type.

## Root Cause

`Validator` rebuilt all attribute rules during post-hydration validation.

## Fix

`DtoRuleBuilder::build()` can now skip attribute rules, and `Validator` skips them for the post-hydration type-validation pass.

## Regression Coverage

Added a PHPUnit POC regression test for a `Carbon` field with `#[DateFormat('Y-m-d')]`.
