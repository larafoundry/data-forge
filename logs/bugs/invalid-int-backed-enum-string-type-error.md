# Invalid Int Backed Enum String TypeError

## Status

Fixed.

## Summary

Invalid string input for an int-backed enum could leak a raw `TypeError`.

## Actual Behavior

Strict enum `tryFrom()` calls could throw `TypeError` before validation could report the field error.

## Expected Behavior

Invalid enum input should produce a structured `ValidationException`.

## Root Cause

The enum casting path did not handle backing-type mismatches from `tryFrom()`.

## Fix

The cast path now preserves values that cannot be passed to `tryFrom()`, allowing normal type validation to fail them.

## Regression Coverage

Added a POC regression test for invalid string input on an int-backed enum.
