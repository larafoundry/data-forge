# Strict Input Policy

## Status

Superseded by `logs/improvements/strict-input-trait-api.md`.

## Summary

Added an opt-in strict input policy while preserving non-strict `fromArray()` behavior for existing DTOs.

## Change

- Added `Axiom\DataForge\Contracts\StrictInput`.
- Added `AsDto::fromArrayStrict()`.
- Normal `fromArray()` ignores unknown external keys unless the DTO implements `StrictInput`.
- `fromArrayStrict()` always rejects unknown external keys.
- `MapKey` fields now read only their mapped external key; the property name is treated as unknown external input.

## Regression Coverage

`tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`

- `test_non_strict_from_array_ignores_unknown_payload_keys`
- `test_from_array_strict_rejects_unknown_payload_keys`
- `test_strict_input_dto_rejects_unknown_payload_keys_from_default_from_array`
- `test_mapkey_reads_mapped_external_key`
- `test_mapkey_original_property_key_is_ignored_in_non_strict_mode`
- `test_mapkey_original_property_key_is_rejected_in_strict_mode`

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`
- `vendor/bin/phpunit tests/Unit/Attributes/MapKeyTest.php`
- `composer phpstan`
