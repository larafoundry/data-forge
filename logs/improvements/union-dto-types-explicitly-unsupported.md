# Union DTO Types Explicitly Unsupported

## Summary

Documented that union DTO/object hydration targets are not supported.

## Why

Array payloads do not provide a safe, predictable way to choose between
multiple DTO or object branches during the validation and hydration pipeline.
That ambiguity affects validation rules, mapped input keys, and strict unknown
input checks.

## Documentation Updates

- `README.md`
- `AGENTS.md`
