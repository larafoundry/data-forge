# Alpha Structure Review

Date: 2026-06-02

## Verdict

The source tree maps directly to the DTO boundary pipeline without parallel
generation or fixture subsystems.

## Source Namespace Map

- `src/Concerns`: public DTO traits and root entry points.
- `src/Schema`: compiled DTO contract source of truth for fields, input keys,
  rules, messages, defaults, and reflected types.
- `src/Input`: raw input key validation, raw-to-canonical mapping, and
  canonical-to-external error key rendering.
- `src/Validation`: Laravel rule normalization/planning, validation projection,
  and validation pipeline orchestration.
- `src/Hydration`: nested DTO hydration, typed collections, enums, date-like
  casting, and DTO instantiation.
- `src/Attributes`: small declarative contract attributes.
- `src/Exceptions`: package exception hierarchy.

This layout matches the project mental model:

```text
raw input -> strict preflight -> canonical key normalization -> validation/project -> hydration/cast -> typed validation -> DTO instantiation
```

## Structural Strengths

- The public `AsDto` path goes through `DtoInspector`, `Validator`, and
  `ContainerHelper`.
- Raw and canonical namespaces have dedicated helpers:
  `InputMapper`, `UnknownInputKeyValidator`, and `ErrorKeyMapper`.
- Schema compilation is concentrated in `DtoSchemaCompiler`; direct DTO
  `rules()` and `messages()` calls are centralized through the schema contract.
- `ContainerHelper` works with canonical DTO construction data only, which keeps
  external key mapping out of object creation.
- Tests are organized by subsystem:
  `tests/Unit/{Schema,Input,Validation,Hydration,Attributes}` and
  `tests/Feature/{AsDto,DtoPipeline}`.

## Structural Risks

- Carbon is directly imported but only supplied transitively by Illuminate.
- PHPStan scans `src/` only. That is a reasonable core gate, but it does not
  statically check test fixtures.
- `setup.sh` is not a release gate and should not be treated as one.
- There are many docs/logs from Spatie test-port work. They are useful decision
  history, but public alpha docs should have a smaller direct path for users.

## Test Layout Snapshot

- Source PHP files: 31.
- Test files: 44 `*Test.php` files.
- Test PHP files including fixtures: 71.
- Current suite: 280 tests and 667 assertions.

## Recommended Alpha Structure Gate

Before public alpha, the project should have:

- green `composer phpstan`;
- green `composer test`;
- green `composer pint`;
- honest runtime dependency metadata;
- alpha install/version docs;
- a short Laravel migration guide.
