# Nested Array Rule After Hydration

## Bug

Custom Laravel `array` rules on nested DTO and `ArrayOf` collection fields were evaluated after the raw input had already been hydrated.

## Reproduction

Create a DTO with either a nested DTO property or an `#[ArrayOf]` collection and custom rules:

```php
public static function rules(): array
{
    return [
        'person' => 'required|array',
    ];
}
```

Then call `fromArray()` with `person` as a valid raw array.

## Expected

The `array` rule should validate the raw input array, and the field should hydrate into the declared DTO type afterward.

## Actual

The validator hydrated `person` into a DTO object, or `people` into a `Collection`, before Laravel validation ran, causing valid raw arrays to fail the `array` rule.

## Fix

`Validator::from()` now validates custom Laravel rules against raw input first, hydrates/casts attributes after that succeeds, then validates internal PHP type rules against the hydrated result.

## Tests

Added regression coverage in `tests/Feature/AsDto/NestedDtoTest.php`.
