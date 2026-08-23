# Nested Date Format Rule Not Forwarded

## Status

Fixed.

## Summary

A parent DTO rule like `child.date => date_format:d/m/Y` validated the raw nested value, but the rule was not passed into child DTO hydration. The child date caster could therefore miss the declared format and fail post-hydration type validation.

## Reproduction

`tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`

- `test_nested_custom_date_format_rule_drives_nested_date_casting`

## Root Cause

Nested DTO hydration called the child DTO's default `fromArray()` without forwarding parent custom rules below the nested root.

## Fix

Nested hydration now forwards prefixed custom rules and messages to the child validator, so `date_format` participates in nested date casting.

## Verification

- `vendor/bin/phpunit tests/Feature/DtoPipeline/NestedRulesAndInputMappingTest.php`
- `composer phpstan`
