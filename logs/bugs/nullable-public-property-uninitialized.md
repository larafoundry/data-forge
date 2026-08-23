# Nullable Public Property Uninitialized

## Status

Fixed.

## Summary

Missing nullable public properties without defaults could produce DTO instances with uninitialized typed properties.

## Actual Behavior

The DTO was created, but reading the nullable property raised a typed property initialization error.

## Expected Behavior

Missing nullable DTO fields should become `null` or fail loudly; they must not create partial DTOs.

## Root Cause

The inspector treated nullable public properties as optional, but `ContainerHelper` did not initialize missing nullable properties.

## Fix

`ContainerHelper` now initializes uninitialized nullable public properties to `null` after construction.

## Regression Coverage

Added a POC regression test for a missing nullable public property.
