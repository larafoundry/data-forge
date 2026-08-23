# String Caster Null Empty String

## Status

Fixed.

## Summary

`StringCaster` converted `null` into an empty string.

## Actual Behavior

`StringCaster::cast(null)` returned `''`.

## Expected Behavior

The caster should not invent a value for `null`; invalid or absent input should return `null`.

## Root Cause

The caster special-cased null as an empty string.

## Fix

`StringCaster` now returns `null` for null input.

## Regression Coverage

Added a PHPUnit POC regression test for null string casting.
