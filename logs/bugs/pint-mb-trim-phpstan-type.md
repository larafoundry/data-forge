# Pint Mb Trim PHPStan Type

## Status

Fixed.

## Summary

Pint's `mb_str_functions` rule changed `trim()` calls in `DtoRuleBuilder` to
`mb_trim()`. PHPStan then inferred mixed return values for the polyfilled
function and failed level max analysis.

## Expected Behavior

The style configuration should not introduce PHPStan failures or rely on string
helpers that are not typed clearly for the package's PHP 8.1 support window.

## Fix

Disabled the `mb_str_functions` Pint rule and restored `trim()` in
`DtoRuleBuilder`.

## Verification

Run after the fix:

- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist`
- `vendor/bin/phpunit`
- `vendor/bin/pint --test`
