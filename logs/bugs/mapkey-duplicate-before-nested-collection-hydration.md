# MapKey Duplicate Before Nested Collection Hydration

## Status

Superseded by strict input policy.

## Summary

When an input payload contained both the original property key and the mapped key for a nested DTO collection, duplicate mapped-key detection needed to run before collection hydration.

## Example

```php
[
    'people' => 'not-an-array',
    'members' => [
        ['name' => 'Alice'],
    ],
]
```

For a property like:

```php
#[MapKey('members')]
#[ArrayOf(MemberDto::class)]
public readonly Collection $people
```

## Actual Behavior

The old validation flow hydrated attributes in `Validator::from()` before duplicate mapped-key validation. Because original keys are preferred during input-key resolution, `people` was selected and collection hydration failed with a `people` array error before the duplicate key conflict could be reported.

## Expected Behavior

The mapped key is the only valid external source for the property. In non-strict mode, the original property key is ignored as unknown input. In strict mode, the original property key fails before nested collection hydration.

## Root Cause

Duplicate mapped-key detection ran after auto-casting and nested collection hydration, allowing hydration errors to mask the mapping conflict.

## Fix

Strict input validation now rejects the original property key before nested collection hydration. Duplicate `MapKey` definitions still fail under `ValidationException['key_mapping']`.

## Regression Coverage

Updated PHPUnit coverage for a mapped `ArrayOf` collection where strict mode rejects the original property key before hydration.
