# Duplicate MapKey Definitions

## Status

Fixed.

## Summary

Two DTO properties could map to the same input key with `#[MapKey]` and validation would not reject the ambiguous contract.

## Actual Behavior

The reverse key map silently dropped one property mapping.

## Expected Behavior

Multiple DTO properties mapping to the same input key should fail loudly.

## Root Cause

Duplicate checking only considered payloads containing both original and mapped keys, not duplicate mapped keys declared by the DTO itself.

## Fix

Validator duplicate checks now detect multiple properties sharing one input key before validation and hydration proceed.

## Regression Coverage

Added a POC regression test for two properties using `#[MapKey('shared')]`.
