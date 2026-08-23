# Carbon Caster Invalid String Exception

## Status

Fixed.

## Summary

`CarbonCaster` leaked Carbon parse exceptions for invalid date strings.

## Actual Behavior

`CarbonCaster::cast('not-a-date')` threw an invalid format exception.

## Expected Behavior

Invalid transformer input should return `null`, matching the `ITransformer` contract.

## Root Cause

`Carbon::parse()` was called without guarding parse failures.

## Fix

`CarbonCaster` now catches parse failures and returns `null`.

## Regression Coverage

Added a PHPUnit POC regression test for an invalid Carbon date string.
