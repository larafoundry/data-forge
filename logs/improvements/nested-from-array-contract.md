# Nested FromArray Contract

## Status

Applied.

## Summary

Documented and tested that custom `fromArray()` overrides are root-level public
entry points. Nested DTOs and `#[ArrayOf]` items are hydrated through Data
Forge's canonical validator/hydrator pipeline instead of calling the child
class's custom `fromArray()` override.

## Rationale

Keeping nested hydration inside the canonical pipeline preserves one error-key
translation boundary and avoids double-mapping nested validation errors.

## Regression Coverage

`tests/Feature/AsDto/NestedDtoTest.php`

- `test_nested_dto_hydration_does_not_call_custom_from_array_override`

## Documentation

- `README.md`
- `docs.md`
