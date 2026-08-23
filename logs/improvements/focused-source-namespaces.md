# Focused source namespaces

## Context

`src/Core` mixed schema inspection, input mapping, validation, hydration, and
object creation classes in one namespace.

## Change

Split those responsibilities into focused source namespaces:

- `Schema`
- `Input`
- `Validation`
- `Hydration`

No compatibility aliases are kept under `Axiom\DataForge\Core`.
