# DateFormat Invalid Timezone Masked

## Status

Fixed.

## Summary

An invalid `#[DateFormat]` timezone was reported as user input validation
instead of DTO configuration failure.

## Actual Behavior

`#[DateFormat('Y-m-d', timezone: 'Not/AZone')]` caused date casting to fail,
then the failed cast was swallowed and reported as an invalid field type.

## Expected Behavior

Invalid attribute metadata should fail with `InspectionException`. Invalid
date strings supplied by users should continue to fail with
`ValidationException`.

## Fix

`DtoSchemaCompiler` validates configured `DateFormat` timezones while compiling
DTO field metadata and throws `InspectionException` with the timezone exception
chained.

## Regression Coverage

Added pipeline coverage for invalid timezones with string input, omitted
optional input, and already typed date input, all expecting `InspectionException`.
Existing invalid date-string coverage continues to expect `ValidationException`.
