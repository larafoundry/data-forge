# Nested Dotted Rules Rejected

## Bug

Custom Laravel validation rules such as `person.name` were rejected before validation for DTOs that declare a top-level `person` field.

## Reproduction

Create a DTO with a nested DTO property and custom rules:

```php
public static function rules(): array
{
    return [
        'person.name' => 'required|min:3',
    ];
}
```

Then call `fromArray()` with `person` as an input array whose `name` is too short.

## Expected

Validation should run and return a validation error for `person.name`.

## Actual

`DtoRuleBuilder` threw an `InspectionException` because it required custom rule keys to exactly match top-level DTO keys.

## Fix

Custom rule existence checks now validate the root segment of dotted keys, and validator key mapping maps only the root segment so `MapKey` works with rules like `person.name`.

## Tests

Added regression coverage in `tests/Feature/AsDto/NestedDtoTest.php`.
