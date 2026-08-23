# Strict Input Locality and Numeric Keys

## Summary

Strict input detection leaked through parent classes and strict root payloads
with integer-like unknown keys crashed before producing a validation error.

## Fix

- `DtoClass::rejectsUnknownInputKeys()` now detects `AsStrictInputDto` only on the class
  being inspected, while still supporting trait composition such as
  `AsAppDto -> AsStrictInputDto`.
- `UnknownInputKeyValidator` casts unknown input keys to strings before building
  the error path and message.

## Regression Coverage

- `tests/Feature/AsDto/StrictInputDtoTest.php`
