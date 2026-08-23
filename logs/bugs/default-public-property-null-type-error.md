# Default Public Property Null TypeError

## Status

Fixed.

## Summary

Explicit `null` for a non-nullable public property with a default bypassed type validation and then crashed during assignment.

## Actual Behavior

Validation accepted the value, and `ContainerHelper` raised a raw `TypeError`.

## Expected Behavior

Explicit `null` for a non-nullable field should fail validation.

## Root Cause

The type validation closure skipped null checks whenever a reflected property had a default value.

## Fix

The default-value null bypass was removed; defaults only apply when input is missing.

## Regression Coverage

Added a POC regression test for explicit null on a non-nullable default public property.
