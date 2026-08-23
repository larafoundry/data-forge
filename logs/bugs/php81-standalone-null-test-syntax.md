# PHP 8.1 Test Suite Failed On Standalone `null` Closure Return Type

## Status

Fixed.

## Summary

One unit test declared a closure with return type `: null`, which is valid only
on PHP 8.2+ and caused the PHP 8.1 test job to fail before the suite could run.

## Reproduction

`tests/Unit/Validation/LaravelRuleObjectTest.php`

- `test_closure_rules_are_rejected`

The closure was only a fixture for unsupported-rule detection, but PHP 8.1
failed while parsing the file.

## Expected Behavior

The test suite should parse and run across the supported PHP matrix, including
PHP 8.1.

## Root Cause

Standalone `null` types were introduced in PHP 8.2. The repository still tests
against PHP 8.1, where `: null` is a syntax error.

## Fix

Replaced the fixture with a normal `Closure` using a PHP 8.1-compatible `void`
return type. `DtoRuleBuilder` only checks `instanceof Closure`, so the test
coverage remains the same.

## Verification

- `vendor/bin/phpunit tests/Unit/Validation/LaravelRuleObjectTest.php`
- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist`
