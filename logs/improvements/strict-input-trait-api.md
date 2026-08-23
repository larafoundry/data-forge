# Strict Input Trait API

## Status

Applied.

## Summary

Replaced the pre-release strict input surface with a trait-based API:
`AsDto` remains flexible by default, while `AsStrictInputDto` rejects unknown
raw input keys for the DTO class that uses it.

## Change

- Added `Axiom\DataForge\Concerns\AsStrictInputDto`.
- Removed `Axiom\DataForge\Contracts\StrictInput`.
- Removed `AsDto::fromArrayStrict()`.
- Centralized strict detection in `DtoClass::rejectsUnknownInputKeys()`.
- Kept strictness local to each DTO class; strict parents do not force nested
  DTOs or collection items to become strict.

## Regression Coverage

- `tests/Feature/AsDto/StrictInputDtoTest.php`
- `tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`
- `tests/Unit/Input/NestedStrictErrorKeyTest.php`
- `tests/Unit/Attributes/MapKeyTest.php`
