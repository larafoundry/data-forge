# Non Array Rules Messages Swallowed

## Status

Fixed.

## Summary

DTO `rules()` and `messages()` hooks returning non-arrays were silently treated
as absent hooks.

## Actual Behavior

`DtoContract` converted non-array hook results to `[]`, which could disable
validation or drop custom messages without warning.

## Expected Behavior

Malformed static DTO contracts should fail loudly as `InspectionException`.

## Fix

`DtoContract` now throws `InspectionException` when `rules()` or `messages()`
returns a non-array value.

## Regression Coverage

Added schema coverage for non-array `rules()` and non-array `messages()`
results.
