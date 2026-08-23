# Default Validation Messages Leak Translation Keys

## Status

Fixed.

## Summary

Laravel validation failures could expose translation keys such as
`validation.required` instead of readable validation messages.

## Actual Behavior

The package created a validation factory with an empty `ArrayLoader`, so
default Laravel validation messages were unavailable.

## Expected Behavior

Validation errors should contain plain, stable, human-readable strings unless
callers provide custom messages.

## Fix

The default validation factory now loads Laravel's bundled English validation
messages into `ArrayLoader`. If the bundled file is unavailable, the loader
falls back to its previous empty state.

## Regression Coverage

Added coverage proving `required` renders as `The name field is required.` and
custom messages still override the defaults.
