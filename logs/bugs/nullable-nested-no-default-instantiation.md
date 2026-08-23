# Nullable Nested No Default Instantiation

## Status

Fixed.

## Summary

Nullable constructor parameters without default values were treated as optional by validation but still failed during DTO instantiation when omitted.

## Example

```php
public function __construct(
    public readonly string $title,
    public readonly ?BasicDto $person
) {}
```

Input:

```php
[
    'title' => 'Manager',
]
```

## Actual Behavior

Validation passed because nullable parameters are considered optional, but `ContainerHelper` threw an `InstantiationException` for the missing `person` parameter.

## Expected Behavior

The DTO should be instantiated with `person` set to `null`.

## Root Cause

`DtoInspector` and `ContainerHelper` used different optional-parameter rules. `DtoInspector` treated nullable parameters as optional, while `ContainerHelper` only accepted explicit defaults.

## Fix

`ContainerHelper` now passes `null` for omitted constructor parameters whose declared type allows null.

## Regression Coverage

Added a PHPUnit test covering an omitted nullable nested DTO constructor parameter without a default value.
