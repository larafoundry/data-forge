# Invalid Backed Enum String Throws Raw ValueError

## Status

Fixed.

## Summary

Invalid string input for a backed enum field threw a raw `ValueError` instead of returning a structured `ValidationException`.

## Example

```php
ComplexObject::fromArray([
    'enumType' => 'deleted',
]);
```

## Actual Behavior

PHP threw:

```text
ValueError: "deleted" is not a valid backing value
```

## Expected Behavior

The invalid value should fail DTO validation and return a structured `ValidationException`.

## Root Cause

`Validator::castValue()` used `BackedEnum::from()` for enum auto-casting. `from()` throws `ValueError` when a string does not match an enum backing value.

## Fix

Changed enum auto-casting to use `tryFrom()`. If no enum case matches, the original value is preserved so the existing type validation rule can report a structured validation error.

## Regression Coverage

Added a PHPUnit test ensuring invalid enum strings fail with `ValidationException`.
