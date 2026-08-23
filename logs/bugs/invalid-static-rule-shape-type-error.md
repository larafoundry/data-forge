# Invalid Static Rule Shape Type Error

## Status

Fixed.

## Summary

Invalid DTO static rule shapes could leak raw `TypeError`.

## Actual Behavior

A numeric rule key eventually reached `explode()` and threw a native `TypeError`.

## Expected Behavior

Invalid DTO rule configuration should fail with `InspectionException`.

## Root Cause

`Validator::withRules()` and `DtoRuleBuilder` trusted rule keys to be strings at runtime.

## Fix

Rule and message ingestion now validates runtime key/value shapes and throws `InspectionException` for invalid configuration.

## Regression Coverage

Added a PHPUnit POC regression test for a numeric static rule key.
