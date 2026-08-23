# Mixed Type Rejects Array Payload

## Bug

DTO fields typed as `mixed` rejected non-null payloads such as arrays because
runtime type validation compared the PHP value type to the literal type name
`mixed`.

## Reproduction

`tests/Feature/DtoPipeline/CoreValidationTest.php`

- `test_mixed_typed_field_accepts_array_payload`

## Fix

`DtoInspector` now treats `mixed` as accepting every value.

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/CoreValidationTest.php`
