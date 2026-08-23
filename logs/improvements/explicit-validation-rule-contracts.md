# Explicit Validation Rule Contracts

## Status

Implemented.

## Context

Laravel 10 introduced `Illuminate\Contracts\Validation\ValidationRule` as the
preferred custom validation rule contract while keeping
`Illuminate\Contracts\Validation\Rule` for legacy compatibility. Data Forge
should support both deliberately, not as an accidental catch-all.

## Decision

Static `rules()` accepts:

- `ValidationRule` for new custom rules.
- Legacy `Rule` for Laravel compatibility.
- `Stringable` rule objects for Laravel's stringable rule builders.

Closures, nested rule arrays, Spatie-style validation attributes, and DB-backed
`Rule::exists()`/`Rule::unique()` objects remain unsupported.
