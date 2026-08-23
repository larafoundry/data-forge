# MapKey Duplicate Input Silently Accepted

## Status

Superseded by strict input policy.

## Summary

When input contained both a DTO property name and its mapped key, validation silently accepted the input and ignored one of the values.

## Example

```php
[
    'first_name' => 'John',
    'firstName' => 'Jane',
    'age' => 30,
]
```

For a property like:

```php
#[MapKey('first_name')]
public readonly string $firstName
```

## Actual Behavior

Validation passed and returned only one value for `firstName`, silently dropping the conflicting input.

## Expected Behavior

The mapped key is the only valid external source for the property. In non-strict mode, the original property key is ignored as unknown input. In strict mode, the original property key fails as an unknown external key.

## Root Cause

Duplicate detection ran after Laravel validation returned validated data. At that point, the conflicting original key could already be omitted, so the duplicate was no longer visible.

## Fix

MapKey source resolution now reads only the mapped key. Duplicate `MapKey` definitions still fail under `ValidationException['key_mapping']`, but input containing both the property name and mapped key is no longer treated as a mapping-contract duplicate.

## Regression Coverage

Updated PHPUnit coverage for non-strict ignored original keys and strict unknown-key failures.
