# Optional Date Empty String Casts To Now

## Bug

Optional date-like DTO fields such as `?Carbon` accepted an empty string and
`Carbon::parse('')` converted it to the current timestamp.

## Reproduction

`tests/Feature/DtoPipeline/CoreValidationTest.php`

- `test_empty_string_for_optional_datetime_becomes_null_instead_of_current_datetime`

## Fix

Date auto-casting now treats blank strings like Laravel request data: nullable
date-like fields hydrate to `null` instead of the current time.

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/CoreValidationTest.php`
