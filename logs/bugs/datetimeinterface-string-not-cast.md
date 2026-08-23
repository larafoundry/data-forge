# DateTimeInterface String Not Cast

## Bug

DTO fields typed as `DateTimeInterface` rejected parseable date strings even
though date-like string auto-casting is part of the package contract.

## Reproduction

`tests/Feature/DtoPipeline/CoreValidationTest.php`

- `test_date_time_interface_accepts_parseable_date_string`

## Fix

Date auto-casting now handles `DateTimeInterface` targets by hydrating a
date-like object that satisfies the interface.

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/CoreValidationTest.php`
