# Duplicate MapKey Reported As Validation

## Status

Fixed.

## Summary

Duplicate `#[MapKey]` input keys were reported as caller input validation
errors.

## Actual Behavior

`InputMapper` detected duplicate mapped keys during normalization and threw a
`ValidationException` with a synthetic `key_mapping` error.

## Expected Behavior

Duplicate input-key declarations are DTO configuration errors and should fail
as `InspectionException` during schema inspection.

## Fix

Duplicate input-key detection moved into `DtoSchemaCompiler`. `InputMapper`
now only normalizes external keys to canonical keys.

## Regression Coverage

Added schema coverage for duplicate mapped keys and mapped-key collisions with
unmapped field names, plus pipeline coverage expecting `InspectionException`.
