# Nested Collection Validation Stops At First Invalid Item

## Status

Resolved.

## Summary

When multiple nested DTO records inside an `#[ArrayOf]` collection are invalid,
Data Forge currently reports the first invalid item and stops hydrating the
remaining collection items.

## Actual Behavior

Given a collection payload where two items are missing the same required nested
field, the error bag contains only the first item path:

```php
[
    'profiles.0.name' => ['Fix the nested name.'],
]
```

The second invalid item path, such as `profiles.1.name`, is not reported.

## Expected Decision

Decide whether Data Forge should keep fail-fast nested collection hydration or
aggregate validation errors across all invalid collection items like Laravel's
array validation and Spatie's collection validation scenarios.

## Reproduction

The behavior was confirmed while porting Spatie nested collection message
scenarios to `tests/Feature/DtoPipeline/SpatieValidationMessagesPortTest.php`.

## Resolution

Nested `#[ArrayOf]` DTO collection hydration now collects validation errors from
all invalid item arrays before throwing one `ValidationException`. This matches
Laravel/Spatie-style array validation error aggregation while still preventing
partial DTO creation.
