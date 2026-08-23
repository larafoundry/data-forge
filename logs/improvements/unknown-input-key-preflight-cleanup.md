# Unknown input key preflight cleanup

## Context

Strict unknown-key checking should be owned by the root validation boundary, where
raw input keys are still available for every nested DTO path.

## Change

- Renamed `StrictInputValidator` to `UnknownInputKeyValidator`.
- Renamed the internal strict detection API to
  `DtoClass::rejectsUnknownInputKeys()`.
- Nested validators created by `DtoHydrator` now skip unknown-key preflight and
  validate only canonical nested data.
- Removed the redundant nested `UnknownInputKeyException` catch/prefix path from
  `DtoHydrator`.

## Verification

- `vendor/bin/phpunit tests/Feature/AsDto/StrictInputDtoTest.php tests/Unit/Attributes/MapKeyTest.php`
