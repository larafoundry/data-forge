# Spatie Laravel Data Test Port

## Summary

Added a Spatie-inspired test-port workflow and initial high-value regression
coverage for Data Forge.

## Details

- Added a manifest classifying all 72 upstream `spatie/laravel-data` test files.
- Added a backlog document for unsupported and decision-required Spatie
  behavior.
- Ported focused validation, mapping, creation, hydration, enum/date casting,
  scalar non-casting, collection shape, nested message, and rule normalization
  scenarios that match Data Forge contracts.

## Contract

The port treats Spatie as an edge-case source, not a compatibility target.
Assertions are written against Data Forge's raw input, canonical key, strict
unknown-key, validation, and hydration boundaries.
