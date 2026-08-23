# Float Field Rejects Int

## Status

Fixed.

## Summary

DTO fields typed as `float` rejected integer numeric input.

## Actual Behavior

`float $amount` with input `['amount' => 1]` failed validation with an
invalid type error.

## Expected Behavior

Integer numeric input should be accepted for `float` fields and PHP should
widen the value to `1.0`. Numeric strings and booleans must still be rejected.

## Fix

`TypeSpec` now accepts `int` values for reflected `float` types without
changing `int` field semantics.

## Regression Coverage

Added unit coverage for `TypeSpec` and pipeline coverage proving integer input
hydrates as a real float while strings, booleans, and lossy float-to-int input
are rejected.
