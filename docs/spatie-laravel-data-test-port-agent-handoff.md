# Spatie Test Port Agent Handoff

Use this handoff when delegating the next test-port wave to `codex2`,
`codex3`, and `claudedanger`.

## Current State

- Upstream reference: `references/spatie-laravel-data`.
- Upstream test files inventoried: 72 `*Test.php` files.
- Upstream Pest test cases counted locally: about 739.
- Data Forge currently has a first executable port slice only, not a full
  compatible-case sweep.
- Existing Spatie-inspired executable test files:
  - `tests/Feature/DtoPipeline/SpatieCreationHydrationPortTest.php`
  - `tests/Feature/DtoPipeline/SpatieNestedValidationPortTest.php`
  - `tests/Feature/DtoPipeline/SpatieValidationMessagesPortTest.php`
  - `tests/Unit/Hydration/SpatieCastPortTest.php`
  - `tests/Unit/Validation/SpatieRulePortTest.php`
- Existing planning/control docs:
  - `docs/DATA.md`
  - `docs/spatie-laravel-data-test-port-plan.md`
  - `docs/spatie-laravel-data-test-port-manifest.md`
  - `docs/spatie-laravel-data-test-port-backlog.md`
- Existing known bug log:
  - `logs/bugs/nested-collection-validation-stops-at-first-invalid-item.md`

## Shared Rules For Every Agent

Read `AGENTS.md`, `docs/DATA.md`, the plan, manifest, and backlog before
editing.

This wave is for expanding tests and logs only. Do not change `src`, public API,
composer dependencies, or Data Forge behavior unless the human explicitly asks.

Spatie is an edge-case source, not a compatibility target. Port the idea, not
the API. Do not import `Spatie\LaravelData` and do not add `spatie/laravel-data`
as a dependency.

Use Data Forge contracts:

- `fromArray(array)`, root-only `fromJson(string)`, and root-only
  `fromArrayable(Arrayable)` only.
- `#[MapKey]` is explicit and flat. Mapped external keys are not aliases.
- Static validation rules support strings, arrays of strings, and explicit
  Laravel rule objects. Prefer `ValidationRule`; legacy `Rule` and `Stringable`
  are Laravel compatibility paths.
- No closures, validation context injection, request/auth/route injection,
  Eloquent, Livewire, Inertia, TypeScript, Lazy, Optional, Spatie DataCollection,
  broad scalar casts, extra magic `from*`, or normalizer pipelines.
- Collections are `Illuminate\Support\Collection` plus explicit `#[ArrayOf]`.
- Nested DTO/custom child `fromArray()` overrides are not called.
- Invalid user input should surface as `ValidationException`.
- Strict unknown input should surface as `UnknownInputKeyException` with raw
  input key paths.
- Inspection/config problems should surface as `InspectionException`.

Passing tests are preferred. If a Spatie-compatible case reveals a likely Data
Forge bug, do not force a source change in this wave. Add a focused bug log in
`logs/bugs/<short-kebab>.md`, and leave the executable test out unless it can
pass under the current contract.

If a case requires a new product decision, update
`docs/spatie-laravel-data-test-port-backlog.md` instead of implementing support.

Run focused tests for touched files. The final reviewer will run full gates:

```bash
composer phpstan
composer test
composer pint
```

Run agents sequentially in the same checkout, or in separate git worktrees if
you want true parallelism. The scopes below are disjoint, but a single checkout
can still get noisy if multiple agents edit, format, and test at the same time.

## Agent 1: codex2 - Validation, Mapping, Messages

Primary goal: expand executable tests for Data Forge-compatible validation and
mapping edge cases.

Read upstream:

- `references/spatie-laravel-data/tests/ValidationTest.php`
- `references/spatie-laravel-data/tests/MappingTest.php`
- `references/spatie-laravel-data/tests/RuleInferrers/RequiredRuleInferrerTest.php`
- `references/spatie-laravel-data/tests/Support/Validation/ValidationPathTest.php`
- `references/spatie-laravel-data/tests/Support/Validation/RuleNormalizerTest.php`

Prefer target files:

- `tests/Feature/DtoPipeline/SpatieNestedValidationPortTest.php`
- `tests/Feature/DtoPipeline/SpatieValidationMessagesPortTest.php`
- new `tests/Feature/DtoPipeline/SpatieMappingPortTest.php`
- new `tests/Feature/DtoPipeline/SpatieRequiredNullablePortTest.php`
- `tests/Unit/Validation/SpatieRulePortTest.php`

Good cases to add:

- Required, nullable, default, and explicit `required` interactions that match
  Data Forge behavior.
- Root and nested mapped-key validation errors rendered with external input
  keys.
- Nested DTO and nested collection validation message paths.
- Wildcard-style message/rule behavior only if Data Forge currently supports it.
- Pipe-string and array-string rule combinations, including regex containing
  `|`.
- Negative guards proving canonical field names are not aliases when `#[MapKey]`
  is present.

Do not add:

- Closures, auth/route/container references, DB validation references,
  redirect/error bag behavior, validation strategy config, morphable data, or
  Spatie-style validation attributes.

Suggested command:

```bash
{ printf 'You are Agent 1: codex2. Execute only the Agent 1 scope below.\n\n'; cat docs/spatie-laravel-data-test-port-agent-handoff.md; } | CODEX_HOME=$HOME/.codex-work2 codex exec -C /home/peter/Workspace/personal/data-forge -m gpt-5.5 -c 'model_reasoning_effort="xhigh"' --dangerously-bypass-approvals-and-sandbox -
```

## Agent 2: codex3 - Creation, Hydration, Schema Reflection

Primary goal: expand executable tests for creation, constructor/default
semantics, hydration, and schema/type inspection.

Read upstream:

- `references/spatie-laravel-data/tests/CreationTest.php`
- `references/spatie-laravel-data/tests/Support/DataClassTest.php`
- `references/spatie-laravel-data/tests/Support/DataPropertyTest.php`
- `references/spatie-laravel-data/tests/Support/DataParameterTest.php`
- `references/spatie-laravel-data/tests/Support/DataPropertyTypeTest.php`
- `references/spatie-laravel-data/tests/Support/DataAttributesCollectionTest.php`

Prefer target files:

- `tests/Feature/DtoPipeline/SpatieCreationHydrationPortTest.php`
- new `tests/Unit/Schema/SpatieSchemaReflectionPortTest.php`
- new `tests/Unit/Schema/SpatieTypeInspectionPortTest.php`
- nearby existing schema tests if the repo already has a better focused home.

Good cases to add:

- Constructor promoted property defaults, public property defaults, nullable
  defaults, readonly constructor data, falsey values, and constructor failure
  boundaries.
- Nested DTO hydration and `Collection` plus `#[ArrayOf]` item hydration.
- Schema/inspection behavior for promoted vs non-promoted fields, defaults,
  repeated attributes where Data Forge has relevant attributes, named/nullable
  types, and unsupported ambiguous object unions.
- Guards proving nested child `fromArray()` overrides are not used.

Do not add:

- Magic `from*`, stdClass/model/json/Arrayable creation, Spatie Optional/Lazy,
  global casts, custom casts, paginator/custom collections, PHPDoc-only runtime
  collection inference, data unions/morph maps, or resource transformation.

Suggested command:

```bash
{ printf 'You are Agent 2: codex3. Execute only the Agent 2 scope below.\n\n'; cat docs/spatie-laravel-data-test-port-agent-handoff.md; } | CODEX_HOME=$HOME/.codex-work3 codex exec -C /home/peter/Workspace/personal/data-forge -m gpt-5.5 -c 'model_reasoning_effort="xhigh"' --dangerously-bypass-approvals-and-sandbox -
```

## Agent 3: claudedanger - Casts, Negative Guards, Backlog QA

Primary goal: expand cast tests and intentional non-support guards, then update
backlog/QA notes where useful.

Read upstream:

- `references/spatie-laravel-data/tests/Casts/DateTimeInterfaceCastTest.php`
- `references/spatie-laravel-data/tests/Casts/EnumCastTest.php`
- `references/spatie-laravel-data/tests/Casts/BuiltinTypeCastTest.php`
- `references/spatie-laravel-data/tests/Casts/EnumerableCastTest.php`
- `references/spatie-laravel-data/tests/Resolvers/NameMappersResolverTest.php`
- `references/spatie-laravel-data/tests/Normalizers/JsonNormalizerTest.php`

Prefer target files:

- `tests/Unit/Hydration/SpatieCastPortTest.php`
- new `tests/Feature/DtoPipeline/SpatieNegativeGuardTest.php`
- `docs/spatie-laravel-data-test-port-backlog.md`

Good cases to add:

- More backed enum success/failure and wrong-type failures.
- Date format success/failure that matches `#[DateFormat]`.
- Negative guards for broad scalar casts: strings must not become ints/bools,
  objects must not become arrays, and ambiguous collections must not infer item
  types.
- Negative guards for class/global name mappers and automatic snake/camel/studly
  mapping.
- Backlog notes for JSON/stdClass/Arrayable creation if a decision is still
  needed.

Do not add:

- Custom casts, global cast registries, unserialize casts, output transformers,
  timezone transformation behavior, Spatie collection classes, or normalizer
  creation support.

Suggested command:

```bash
{ printf 'You are Agent 3: claudedanger. Execute only the Agent 3 scope below.\n\n'; cat docs/spatie-laravel-data-test-port-agent-handoff.md; } | (cd /home/peter/Workspace/personal/data-forge && claude --dangerously-skip-permissions -p --permission-mode bypassPermissions --model claude-opus-4-8 --effort xhigh)
```

## Final Review Checklist

After agents finish, the reviewer should do only integration review and gates:

```bash
git status --short
rg -n "spatie/laravel-data|Spatie\\\\LaravelData" composer.json composer.lock src tests
rg -n "Optional|Lazy|DataCollection|DataCollectionOf|WithCast|WithTransformer|MapInputName|MapOutputName|DataCollectionTypeScriptType" src tests
composer phpstan
composer test
composer pint
git diff -- docs tests logs
```

Review expectations:

- More executable tests than the first 21-case slice.
- No `src` changes unless separately approved.
- No accidental Spatie dependency or imports.
- Any unsupported-but-interesting case is represented in backlog, not silently
  implemented.
- Any confirmed current bug has one focused bug log.
- All required checks pass, with Pint run last.
