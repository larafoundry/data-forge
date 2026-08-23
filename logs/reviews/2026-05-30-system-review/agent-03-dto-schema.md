# Agent 03 — DTO Schema Source Of Truth

## Scope

- Files reviewed:
  - `src/Core/DtoSchemaCompiler.php`
  - `src/Core/DtoSchema.php`
  - `src/Core/FieldSpec.php`
  - `src/Core/TypeSpec.php`
  - `src/Core/DtoInspector.php`
  - `src/Contracts/DtoContract.php`
  - `src/Concerns/AsDto.php`
  - related consumers that read compiled schema, including hydration and projection paths where needed
- Tests reviewed:
  - tests matched by `DtoRuleBuilder`
  - tests matched by `AsDto`
  - `rg "rules\\(|messages\\(" src tests` results
- Out of scope:
  - Implementing fixes
  - Changing schema cache behavior
  - Full validation pipeline review
  - Aggregating other agents' findings

## Commands Run

```bash
git status --short
git diff HEAD
rg "rules\\(|messages\\(" src tests
vendor/bin/phpunit --filter DtoRuleBuilder
vendor/bin/phpunit --filter AsDto
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
```

Result:

- PHPStan: passed, no errors.
- PHPUnit: passed. `DtoRuleBuilder` passed with 8 tests / 38 assertions; `AsDto` passed with 64 tests / 196 assertions.
- Pint/composer validate if run: not run.

## Verdict

Short conclusion: **risky design debt, not an immediate merge blocker**. `DtoSchema` is the main source for compiled metadata/rules/messages, but it is not yet an absolute single source-of-truth for all type semantics and consumer behavior.

## Findings

### F-01 — Static Schema Cache Can Stale Dynamic Rules And Messages

Severity: Medium  
Type: Design smell  
Confidence: Medium  
Root cause: `DtoSchemaCompiler` caches compiled schemas statically, including the result of `rules()` and `messages()`, without invalidation.  
Related symptoms:

- Runtime-config-dependent rules can remain stale in long-running processes.
- Static state changes in DTO rule/message methods may not be reflected after first compile.

Evidence:

- `src/Core/DtoSchemaCompiler.php:31`
- `src/Core/DtoSchemaCompiler.php:66`

Actual behavior:

```php
// First compile captures rules/messages.
DtoSchemaCompiler::compile(ExampleDto::class);

// If ExampleDto::rules() depends on runtime config/static state,
// later calls can keep using the first compiled result.
DtoSchemaCompiler::compile(ExampleDto::class);
```

Expected behavior:

Either `rules()` and `messages()` should be documented and enforced as pure/static schema declarations, or the cache should have a clear invalidation/versioning strategy for dynamic runtime use.

Impact:

- Long-running workers, test processes, or apps with runtime-dependent DTO rules can observe stale validation behavior.
- The schema can appear authoritative while holding outdated rule/message data.

Why this happened:

- Classify cause: design boundary.
- The compiler treats DTO metadata and DTO rule/message output as equally cacheable, but rule/message methods are public extension points and may be dynamic unless explicitly forbidden.

Recommendation:

- Choose one contract:
  - Document `rules()` and `messages()` as pure static declarations safe to cache.
  - Or add an explicit `clearCache()` / cache versioning mechanism.
  - Or cache structural reflection metadata separately from dynamic rule/message output.

### F-02 — Union And Complex Type Semantics Are Not Centralized In `TypeSpec`

Severity: Medium  
Type: Semantic contract issue  
Confidence: Medium  
Root cause: `TypeSpec` can represent union/complex types, but consumers still interpret reflection and supported shapes locally.  
Related symptoms:

- Some consumers primarily handle `ReflectionNamedType`.
- DTO, enum, collection, and builtin semantics are spread across hydration, projection, strict validation, and error mapping paths.
- Unsupported union shapes can fail inconsistently or behave as raw arrays instead of explicit unsupported types.

Evidence:

- `src/Core/TypeSpec.php:26`
- `src/Core/DtoHydrator.php:82`
- `src/Core/ValidationProjector.php`
- `src/Core/StrictInputValidator.php`
- `src/Core/ErrorKeyMapper.php`

Actual behavior:

```php
final class ExampleDto
{
    public ChildDto|array $child;
}

// TypeSpec can express a union shape, but hydration/projection/strict descent
// do not share one central support decision for this semantic case.
```

Expected behavior:

The schema layer should expose one authoritative semantic view for supported field shapes, such as DTO class, enum class, collection item class, builtin scalar, nullable, and unsupported union/complex type.

Impact:

- Consumers can disagree about whether a type should be descended into, hydrated, projected, or rejected.
- Unsupported types may fail later and less clearly than an upfront schema/inspection failure.
- Future type support will require coordinated edits across several consumers.

Why this happened:

- Classify cause: semantic mismatch and tight coupling.
- `TypeSpec` stores type structure, but behavior-level helpers and support decisions still live in consumer code.

Recommendation:

- Move semantic helpers into `TypeSpec` or schema-level APIs, for example `dtoClass()`, `enumClass()`, `collectionItemClass()`, `isSupportedHydrationShape()`, and `unsupportedReason()`.
- Reject unsupported union/complex shapes explicitly with `InspectionException`, or implement deterministic support in one place and make consumers use that same decision.

### F-03 — Some Consumers Bypass `DtoInspector` For Schema Reads

Severity: Low  
Type: Tight coupling  
Confidence: High  
Root cause: Some consumers read `DtoSchemaCompiler::compile(...)->rules/messages` directly instead of going through the read API.  
Related symptoms:

- `DtoInspector` is not the only read boundary over schema data.
- Consumer code depends on schema storage details.

Evidence:

- `src/Core/DtoHydrator.php:130`

Actual behavior:

Consumers can compile and read schema fields directly even when `DtoInspector` offers the intended read API.

Expected behavior:

Schema consumers should preferably use one read abstraction, or receive an already compiled schema/inspector from the pipeline, so source-of-truth access is consistent.

Impact:

- This is not a direct behavior bug.
- It weakens the role of `DtoInspector` as the stable schema read boundary.
- Future schema representation changes will require more consumer edits.

Why this happened:

- Classify cause: tight coupling.
- The compiled schema object is easy to access directly, so read logic has spread into consumers.

Recommendation:

- Route rules/messages and other schema reads through `DtoInspector`, or pass a compiled schema/inspector consistently through the pipeline.
- Keep direct compiler access concentrated in schema construction boundaries.

## False Positives Checked

- FP-01: No direct `DtoContract::rules()` or `DtoContract::messages()` calls were found in `src` outside `DtoSchemaCompiler`.
- FP-02: Public pipe-string and array-of-string rule declarations still appear supported.
- FP-03: Rule objects, closures, and `ValidationRule` instances are unsupported and fail clearly enough through inspection/normalization rather than silently changing behavior.
- FP-04: `AsDto` and validation paths generally use compiled schema metadata rather than re-reflecting DTO declarations independently.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
| --- | --- | --- | --- |
| Dynamic rules/messages with schema cache | Rule builder and `AsDto` tests pass | No test proves cache behavior for runtime-dependent rules | Add a DTO whose rules change through controlled static/config state and assert documented cache behavior |
| Unsupported union DTO hydration semantics | TypeSpec can represent unions | No clear regression for union DTO/array support or rejection | Add a union field fixture and assert either deterministic hydration or `InspectionException` |
| Consumer consistency through `DtoInspector` | Direct-call search checks `rules()` / `messages()` | No guard against new direct schema reads | Add architectural/static test or code review rule for schema read boundaries |
| Rule/message normalization single owner | Normalization tests cover current behavior | Normalization logic still appears in more than one consumer path | Add focused tests that compare compiler and validator constraints for the same DTO |

## Open Questions

- Are DTO `rules()` and `messages()` intended to be pure declarations, or can they depend on runtime config/state?
- Should unsupported union and complex type combinations fail during schema compilation, or only when a consumer tries to hydrate/project them?
- Is `DtoInspector` meant to be the only public read API over schema, or is direct access to compiled `DtoSchema` acceptable for internal consumers?

## Final Assessment

`DtoSchema` is the real source for compiled field metadata and declared validation rules/messages in the main code path, and direct calls to DTO `rules()` / `messages()` are correctly concentrated in `DtoSchemaCompiler`. The remaining weakness is that schema authority stops short of fully owning runtime cache semantics and type behavior semantics. If those contracts are clarified and centralized, the schema layer can become a stronger and safer single source-of-truth.
