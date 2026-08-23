# Canonical Input Validation Pipeline

## Summary

Refactored DTO boundary handling so `MapKey` is resolved once at input
normalization time. Validation, rule building, nested hydration, and object
instantiation now operate on canonical DTO property keys instead of repeatedly
mapping between external input keys and internal property names.

## Previous Design Issue

`MapKey` handling was spread across `Validator`, `DtoRuleBuilder`, and
`ContainerHelper`. That forced normal validation and instantiation code to know
about external input keys, creating overlapping logic for rule paths, error
paths, nested DTO hydration, and collection item validation.

## New Design

- `InputMapper` owns external input key to canonical DTO property key mapping.
- `DtoSchema`, `FieldSpec`, and `TypeSpec` provide a central compiled contract
  graph for DTO fields, input keys, type behavior, nested collections, rules,
  and messages.
- `ValidationProjector` converts raw nested arrays, DTO instances, and
  collections into a canonical validation view before Laravel validation.
- `Path` and `RuleScope` centralize nested and collection rule scoping needed for
  hydration-time casting hints.
- `DtoRuleBuilder` builds rules only for canonical DTO property paths.
- `ContainerHelper` instantiates objects only from canonical property-keyed
  data.
- `DtoContract` centralizes static `rules()` / `messages()` loading for the
  validation pipeline.

## Verification

- `composer phpstan`
- `composer test`
- `composer pint`
