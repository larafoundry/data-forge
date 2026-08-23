# Nullable Nested DTO Collection Rejects Explicit Null

## Status

Fixed.

## Summary

DTO fields using `#[ArrayOf(...)]` with nullable `?Collection` rejected explicit `null` input.

## Example

```php
public function __construct(
    public readonly string $title,
    #[ArrayOf(UserDto::class)]
    public readonly ?Collection $people = null,
) {}
```

```php
TeamDto::fromArray([
    'title' => 'Core Team',
    'people' => null,
]);
```

## Actual Behavior

Validation failed with:

```text
The people field must be an array.
```

## Expected Behavior

The DTO should be created successfully and `$dto->people` should remain `null`.

## Root Cause

`Validator::autoCastAttributes()` called `hydrateDtoCollection()` for `#[ArrayOf]` fields before checking whether the field value was `null` and the declared type allowed null.

## Fix

Added a nullable guard before collection hydration. If the value is `null` and the type allows null, hydration is skipped and the null value is preserved.

## Regression Coverage

Added a PHPUnit test for nullable nested DTO collections with explicit null input.
