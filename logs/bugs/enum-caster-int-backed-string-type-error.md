# Enum Caster Int Backed String Type Error

## Status

Fixed.

## Summary

`EnumCaster` leaked a raw `TypeError` for invalid string input passed to an int-backed enum.

## Actual Behavior

`EnumCaster(IntEnum::class)->cast('invalid')` threw `TypeError`.

## Expected Behavior

Invalid transformer input should return `null`, matching the `ITransformer` contract.

## Root Cause

The caster used `BackedEnum::from()` and only caught `ValueError`.

## Fix

The caster now uses `tryFrom()` and catches backing-type mismatches.

## Regression Coverage

Added a PHPUnit POC regression test for invalid string input on an int-backed enum caster.
