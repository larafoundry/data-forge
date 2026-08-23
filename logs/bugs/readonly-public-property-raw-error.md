# Readonly Public Property Raw Error

## Status

Fixed.

## Summary

Hydrating a public readonly property outside the constructor leaked a raw PHP `Error`.

## Actual Behavior

`ContainerHelper` attempted post-construction assignment and PHP raised a readonly property error.

## Expected Behavior

Instantiation failures should be reported through `InstantiationException`.

## Root Cause

Post-construction property assignment was not guarded for readonly properties and did not wrap assignment failures.

## Fix

`ContainerHelper` now rejects readonly post-construction assignment and wraps public property assignment failures in `InstantiationException`.

## Regression Coverage

Added a POC regression test for DTO hydration with a public readonly property.
