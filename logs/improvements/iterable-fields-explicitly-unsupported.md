# Iterable Fields Explicitly Unsupported

## Improvement

`iterable` typed DTO fields are intentionally not supported by `AsDto` because
item type and traversal behavior are ambiguous at the data boundary.

## Change

The POC coverage and documentation now state the supported alternatives:

- use `array` for raw arrays
- use `Illuminate\Support\Collection` with `#[ArrayOf]` for typed nested DTO
  collections

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/CoreValidationTest.php`
