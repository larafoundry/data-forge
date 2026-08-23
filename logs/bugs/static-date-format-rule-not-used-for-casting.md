# Static Date Format Rule Not Used For Casting

## Status

Fixed.

## Summary

Custom DTO rules using `date_format` validated raw input but did not guide date casting.

## Actual Behavior

A `Carbon $date` field with `rules(): ['date' => 'date_format:d/m/Y']` accepted raw `31/12/2024` during pre-validation, then failed post-hydration type validation because casting still used generic parsing.

## Expected Behavior

The declared `date_format` rule should participate in boundary casting for date-like target types.

## Root Cause

Date casting only inspected the `#[DateFormat]` attribute and ignored static/custom validation rules.

## Fix

Date casting now falls back to a field's custom `date_format` rule when no `#[DateFormat]` attribute is present.

## Regression Coverage

Added a regression test in `tests/Unit/Validation/ValidationLogicTest.php`.
