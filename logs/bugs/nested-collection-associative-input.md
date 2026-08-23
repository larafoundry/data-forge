# Nested Collection Associative Input

## Status

Fixed.

## Summary

Nested DTO collections declared as `Collection<int, Dto>` accepted associative arrays and preserved string keys.

## Example

```php
[
    'title' => 'Team',
    'people' => [
        'lead' => [
            'name' => 'John Doe',
            'age' => 30,
        ],
    ],
]
```

## Actual Behavior

The associative input was hydrated into a collection with the `lead` key, producing a `Collection<string, BasicDto>` for a field documented as `Collection<int, BasicDto>`.

## Expected Behavior

Nested DTO collection input should be a list of DTO records. Associative arrays should fail validation at the collection field path.

## Root Cause

`Validator::hydrateDtoCollection()` checked that the value was an array, but did not check that the array was a list before hydrating and preserving its keys.

## Fix

`Validator::hydrateDtoCollection()` now rejects non-list arrays with a `ValidationException` at the collection field path.

## Regression Coverage

Added a PHPUnit test covering associative array input for a nested DTO collection field.
