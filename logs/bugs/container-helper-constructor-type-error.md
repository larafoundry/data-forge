# Container Helper Constructor Type Error

## Status

Fixed.

## Summary

Constructor type mismatches in `ContainerHelper` leaked raw `TypeError`.

## Actual Behavior

Passing an invalid constructor argument type raised PHP's native `TypeError`.

## Expected Behavior

Instantiation failures should be wrapped in `InstantiationException`.

## Root Cause

`ContainerHelper` only caught `ReflectionException` around object construction.

## Fix

Constructor instantiation now catches all `Throwable` and chains it into `InstantiationException`.

## Regression Coverage

Added a PHPUnit POC regression test and updated the existing ContainerHelper test expectation.
