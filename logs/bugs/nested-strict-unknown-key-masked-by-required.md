# Nested Strict Unknown Key Masked By Required

## Status

Fixed.

## Summary

A strict nested DTO could lose an unknown raw input key before nested strict
validation ran. The parent pre-hydration projection normalized nested input in
non-strict mode, dropped the bad key, and let a later required-field validation
report the parent path instead of the caller's actual unknown key.

## Example

```php
[
    'child_data' => [
        'firstName' => 'Jane',
    ],
]
```

For a nested strict DTO with:

```php
#[MapKey('first_name')]
public readonly string $firstName
```

## Actual Behavior

The error was reported as `child_data => validation.required`.

## Expected Behavior

The error should preserve the raw key sent by the caller:
`child_data.firstName => The firstName field is not allowed.`

## Root Cause

Strict unknown-key validation lived inside `InputMapper::normalize()`, which
meant nested strict validation ran during hydration. Pre-hydration projection
had already dropped the raw unknown key before hydration was reached.

## Fix

Added a recursive strict-input preflight that scans raw input before
normalization, projection, validation, or hydration. Strict unknown-key errors
are emitted in the caller's input namespace and are not passed through
`ErrorKeyMapper`.

## Regression Coverage

`tests/Unit/Input/NestedStrictErrorKeyTest.php`

- `test_nested_strict_unknown_key_is_not_masked_by_required_validation`
- `test_nested_strict_unknown_key_preserves_caller_key_through_mapped_path`
- `test_nested_strict_unknown_key_in_collection_is_not_masked_by_required_validation`
- `test_nested_strict_unknown_key_in_collection_preserves_caller_key`
