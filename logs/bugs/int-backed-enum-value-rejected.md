# Int Backed Enum Value Rejected

## Status

Fixed.

## Summary

DTO fields typed as int-backed enums rejected valid integer backing values.

## Actual Behavior

Input like `['status' => 1]` failed type validation instead of hydrating the enum.

## Expected Behavior

Valid backed enum values should be converted to enum instances at the boundary.

## Root Cause

Enum auto-casting only attempted `tryFrom()` for string values.

## Fix

Enum auto-casting now attempts backed enum casts for both string and integer scalar values.

## Regression Coverage

Added a POC regression test proving an int-backed enum accepts its integer backing value.
