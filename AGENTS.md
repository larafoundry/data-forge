# Project Agent Instructions

This file is for agents working in this repository. Keep it short enough to
actually read before making changes.

## Required Checks

Always respect PHPStan.

Before commit:

1. Run PHPStan and fix all errors:
   - `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist`
   - or `composer phpstan`
2. Run tests and fix all failures:
   - This is a Composer package, so use `vendor/bin/phpunit` or `composer test`.
3. Run Laravel Pint last:
   - `vendor/bin/pint`
   - or `composer pint`

## Core Mental Model

Data Forge builds DTOs from unknown array input:

`raw input -> strict preflight -> normalize to canonical keys -> validate raw/projected shape -> hydrate/cast -> validate typed data -> instantiate DTO`

Important boundaries:

- **Raw input keys** are the keys the caller sent.
- **Canonical keys** are DTO property/constructor names.
- `#[MapKey]` maps raw external keys to canonical DTO keys.
- Successful validation returns canonical data for DTO construction.
- Root validation errors are rendered back to external input keys.
- Strict unknown-key errors are already raw input-key errors and must not be
  translated as canonical paths.

## Key Components

- `DtoSchemaCompiler`, `DtoSchema`, `FieldSpec`, and `TypeSpec` are the cached
  schema source of truth for DTO fields, rules, messages, types, defaults, and
  input keys.
- `DtoInspector` is the read API over the compiled schema.
- `DtoContract` is the only place that should call DTO static `rules()` and
  `messages()`.
- `InputMapper` should only normalize external input keys to canonical keys.
- `UnknownInputKeyValidator` recursively checks raw input for unknown keys before
  projection or hydration can drop them.
- `ValidationProjector` builds canonical validation views of nested arrays,
  DTOs, collections, enums, and date-like values.
- `Validator` orchestrates the validation pipeline and owns the root boundary
  where canonical validation errors become external input-key errors.
- `ErrorKeyMapper` translates canonical error paths to external input-key paths.
- `DtoHydrator` hydrates nested DTOs, typed collections, backed enums, and
  date-like values.
- `ContainerHelper` instantiates DTOs from canonical data only; it should not
  know about external `MapKey` input names.

## Behavioral Contracts

- Non-strict `fromArray()` ignores unknown raw input keys.
- DTOs using `AsStrictInputDto` reject unknown raw input keys.
- For mapped fields, the mapped external key is the accepted input key. The
  canonical property name is not an alias.
- Nested DTOs and `#[ArrayOf]` items use the canonical validator/hydrator
  pipeline. Custom `fromArray()` overrides are root-level entry points only.
- Nested values already instantiated as the expected DTO class are treated as
  trusted canonical objects. `fromArray()` preserves them and does not rerun
  the child DTO's static `rules()` or custom `fromArray()` override; parent
  dotted rules still validate at the root boundary.
- Nested `#[ArrayOf]` DTO collection validation aggregates all invalid item
  errors; do not stop after the first invalid item.
- Union DTO/object hydration targets are unsupported. Types such as
  `UserDto|AdminDto`, `object|UserDto`, or similar ambiguous object unions
  should not be treated as supported nested input contracts.
- Static validation rules support pipe strings, flat arrays of strings, and
  explicit Laravel rule objects: prefer `ValidationRule` for custom rules;
  legacy `Rule` and `Stringable` objects are accepted for Laravel compatibility.
  Closures, nested rule arrays, Spatie-style validation attributes, and DB-backed
  `Rule::exists()`/`Rule::unique()` objects are unsupported in the current scope.
- `fromJson()` and `fromArrayable()` are root-only entry points that normalize
  to arrays before calling the normal `fromArray()` pipeline. Request/model,
  stdClass, and magic `from*` normalizers remain unsupported.
- Direct PHP constructor calls such as `new UserDto(...)` are outside the
  validation boundary. Validation runs through root entry points like
  `fromArray()`, `fromJson()`, and `fromArrayable()` only.
- Builtin scalar fields are validated by type; they are not broadly auto-cast
  from strings. Backed enums and date-like objects are cast by `DtoHydrator`.
- Runtime collection item types are never inferred from PHPDoc/docblocks; use
  `#[ArrayOf]` for typed DTO collections.
- Spatie-style `DataCollection`, paginated/cursor collections, and custom
  collection wrapper APIs are outside the current package scope.
- Custom casts, global casts, scalar coercion, and collection coercion are not
  planned unless the public contract is explicitly changed.
- Boundary validation must not be disabled, downgraded, or made best-effort.
- Invalid user input should surface as `ValidationException`.
- Unknown strict input should surface as `UnknownInputKeyException`, preserving
  the exact raw key path.
- Inspection/configuration problems should surface as `InspectionException`.
- Instantiation failures should surface as `InstantiationException`.

## When Changing Code

Prefer the existing pipeline over new side paths. If a change touches key
mapping, nested DTOs, collections, or validation errors, think through both
namespaces: raw external keys and canonical DTO keys.

Focused areas:

- DTO schema/reflection: `src/Schema/DtoSchemaCompiler.php`,
  `src/Schema/DtoInspector.php`, `src/Schema/TypeSpec.php`.
- Input mapping/errors: `src/Input/InputMapper.php`,
  `src/Input/UnknownInputKeyValidator.php`, `src/Input/ErrorKeyMapper.php`.
- Validation flow: `src/Validation/Validator.php`,
  `src/Validation/ValidationProjector.php`, `src/Validation/RuleScope.php`,
  `src/Validation/RulePlanner.php`.
- Hydration/casting: `src/Hydration/DtoHydrator.php`.
- Object creation: `src/Hydration/ContainerHelper.php`.
- Public API: `src/Concerns/AsDto.php`.

Use the nearest focused tests first, then run the required checks before
commit. Keep the pull request linked to the issue or discussion that explains
the confirmed bug or intentional improvement.

## Documentation

Keep public docs aligned with the contracts above:

- examples must match the current public API
- validation error examples should use external input keys
- successful validated data and DTO construction should use canonical keys
- nested DTO docs should mention that nested hydration does not call custom
  child `fromArray()` overrides
