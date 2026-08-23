# Invalid Date Cast Inspection Exception

## Status

Fixed.

## Summary

Invalid date strings for date-like DTO fields raised `InspectionException` during auto-casting instead of returning structured validation errors.

## Actual Behavior

`Carbon::parse()` or native date constructors could throw during casting and surface as inspection failures.

## Expected Behavior

Invalid user input should fail validation with `ValidationException`.

## Root Cause

`Validator::castValue()` converted date parse failures into `InspectionException`.

## Fix

Date cast failures now preserve the original value so the post-hydration type validation reports a `ValidationException`.

## Regression Coverage

Added a POC regression test for an invalid Carbon string input.
