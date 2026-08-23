# Date Format Not Used For Casting

## Bug

`#[DateFormat]` validated raw input but date hydration still used
`Carbon::parse()` or native constructors. Valid non-ISO formats such as
`31/12/2024` passed validation and then failed type validation after hydration.

## Reproduction

`tests/Feature/DtoPipeline/CoreValidationTest.php`

- `test_date_format_attribute_drives_carbon_casting_for_non_iso_format`
- `test_date_format_attribute_rejects_string_that_does_not_match_format`

## Fix

Date auto-casting now uses the declared `#[DateFormat]` format when hydrating
date-like target types.

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/CoreValidationTest.php`
