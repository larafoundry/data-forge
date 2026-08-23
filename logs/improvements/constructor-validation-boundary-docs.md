# Constructor Validation Boundary Docs

## Summary

Clarified in the docs and regression tests that direct PHP constructor calls
and already-instantiated nested DTO instances are outside child revalidation
boundaries.

## Why

Readers could reasonably assume that constructor-promoted DTOs validate on
instantiation, or that `fromArray()` will rerun child DTO rules even when a
nested DTO instance is passed in directly. In practice, validation only runs on
raw/root entry-point payloads such as `fromArray()`, `fromJson()`, and
`fromArrayable()`. Nested DTO instances that already match the expected class
are preserved as trusted objects.

## Updated

- `README.md`
- `AGENTS.md`
- `tests/Feature/DtoPipeline/SpatieCreationHydrationPortTest.php`

## Regression Coverage

`tests/Feature/DtoPipeline/SpatieCreationHydrationPortTest.php`

- `test_preserves_constructor_built_nested_dto_instances_without_rerunning_child_static_rules`
- `test_arrayof_collection_preserves_constructor_built_items_without_rerunning_child_static_rules`
