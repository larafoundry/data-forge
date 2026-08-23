# Spatie Laravel Data Test-Port Backlog

This document records Spatie-inspired behavior that must not become Data Forge
behavior by accident while porting tests from
`references/spatie-laravel-data/tests`.

Spatie is an edge-case source, not a compatibility target. Data Forge remains
bound by `docs/DATA.md` and the pipeline contract in `AGENTS.md`.

## Labels

- `DECISION-BACKLOG`: interesting behavior, but implementing it would create or
  change public Data Forge API. Stop and ask the user before adding support.
- `SKIP`: intentionally outside the package scope. Do not port unless the user
  explicitly changes product direction.
- `NEGATIVE-GUARD`: a small test may be added when it protects an existing Data
  Forge hard contract. A guard must assert Data Forge behavior, not Spatie
  compatibility.

## Decision Backlog

| Divergence | Behavior family | Spatie source examples | User decision required before implementation |
| --- | --- | --- | --- |
| D002 | Dotted, numeric, or mixed-path input mapping. | `MappingTest.php` and `MapInputName('nested.path')`-style fixtures. | Whether Data Forge `#[MapKey]` should remain flat forever, or gain a path syntax with precise raw/canonical error-key rules. |
| D007 remainder | Spatie-style validation attribute expansion, closures, DI/container-aware rules, DB-backed rules, and broader rule denormalization. | `Attributes/Validation/RulesTest.php`, `ValidationAttributeTest.php`, `Support/Validation/RuleDenormalizerTest.php`, DB/context cases in `ValidationTest.php`. | Static `rules()` now accepts explicit Laravel rule objects: prefer `ValidationRule`; legacy `Rule` and `Stringable` remain Laravel compatibility paths. Anything requiring Spatie-style validation attributes, closures, a validation factory/presence verifier, container context, or DB state still needs a separate decision. |
| D008 remainder | Non-array creation inputs beyond accepted root helpers. | Normalizer, request, model, stdClass, and magical creation fixtures. | `fromJson()` and `fromArrayable()` are accepted root-only helpers. `fromStdClass`, request/model normalizers, and magic `from*` methods still need a separate decision. |
| D009, D010 | Runtime collection item inference from PHPDoc or annotations. | `CollectionAttributeWithAnotationsTest.php`, `Support/Annotations/*`, collection portions of `Support/DataPropertyTypeTest.php`. | Whether runtime hydration may infer item DTO classes from PHPDoc/annotations, or must continue requiring `#[ArrayOf]` for nested DTO collections. |
| D009 | Data collection APIs beyond `Illuminate\Support\Collection` plus `#[ArrayOf]`. | `DataCollectionTest.php`, paginated/cursor collection cases, custom collection fixtures. | Whether Data Forge should ever expose a first-class collection wrapper, paginator support, or custom collection factory API. |
| D016 | Validation strategy/configuration knobs. | `CreationFactoryTest.php`, validation strategy/config examples, stop/disable validation behavior. | Whether boundary validation can be disabled or changed per call/config. Default answer is no: Data Forge should fail early. |
| D006 | Broader casting policy changes. | `Casts/BuiltinTypeCastTest.php`, `Casts/EnumerableCastTest.php`, custom/global cast fixtures. | Whether scalar coercion, collection coercion, custom casts, or global casts should be added. Current contract only permits declared boundary casts such as backed enums and date-like objects. |
| Collection aggregation | Multi-item nested collection validation errors. | Collection message scenarios in `ValidationTest.php`. | Accepted: aggregate invalid nested collection item errors like Laravel/Spatie. Tracked in `logs/bugs/nested-collection-validation-stops-at-first-invalid-item.md`. |

## Skip Families

| Divergence | Behavior family | Spatie source examples | Reason to skip |
| --- | --- | --- | --- |
| D003 | Class-level name mappers and implicit snake/camel/studly conversion. | `MappingTest.php`, `Resolvers/NameMappersResolverTest.php`, TypeScript mapper snapshots. | Hidden aliases and key-format guessing conflict with one-field/one-source input rules. |
| D004 | `Optional` and Spatie `sometimes` semantics. | `CreationTest.php`, `TransformationTest.php`, `Support/DataPropertyTypeTest.php`. | Data Forge uses PHP defaults, nullability, and validation rules; it does not have an optional sentinel type. |
| D005 | Lazy/Inertia/deferred values. | `EmptyTest.php`, `Support/Lazy/InertiaLazyTest.php`, lazy property fixtures. | Deferred resource transformation is outside DTO boundary hydration. |
| D007, D015 | Closures, container injection, context injection, route/auth references, and DB validation constraints. | `Attributes/From*`, `DataPipes/*`, `Resolvers/ContextResolverTest.php`, validation tests using route/user/database context. | Framework/application integration would make DTO validation depend on external runtime state. |
| D008 | Request/model normalizers and magic `from*` creation methods. | `RequestTest.php`, `Normalizers/FormRequestNormalizerTest.php`, `Normalizers/ModelNormalizerTest.php`, `MagicalCreationTest.php`. | Current creation contract is explicit `fromArray(array)` at the root boundary. |
| D009 | Spatie `DataCollection`, paginated/cursor collection behavior, and custom collection wrappers. | `DataCollectionTest.php`, collection snapshots, paginator fixtures. | These are Spatie product APIs, not Data Forge boundary primitives. |
| D011 | Nested child custom creation taking over hydration. | Nested creation/custom cast scenarios. | Nested DTOs must use the canonical validator/hydrator pipeline; custom entrypoints are root-level only. |
| D012 | Abstract, morphable, object-union, or DTO-union hydration. | `Resolvers/DataMorphClassResolverTest.php`, `DataClassFromValidationPayloadResolverTest.php`, abstract/morphable fixtures. | Data Forge does not guess object targets or choose subclasses from payloads. |
| D013 | Transformation/resource output features. | `AppendTest.php`, `PartialsTest.php`, `TransformationTest.php`, `Transformers/*`, `WrapTest.php`, `SerializeableTest.php`. | Serialization, wrapping, appending, partials, and transformers are outside input boundary hydration. |
| D014 | Eloquent casts, Livewire, Inertia, Artisan commands, cache commands, and TypeScript generation. | `Support/EloquentCasts/*`, `LivewireTest.php`, `Commands/*`, `Support/TypeScriptTransformer/*`. | These are framework tooling/integration surfaces, not core DTO input handling. |
| D015 | Request, route, auth, model, and container populated properties. | `Attributes/FromAuthenticatedUser*`, `FromRouteParameter*`, `FromContainer*`, `InjectPropertyValuesTest.php`. | The accepted input must be visible in the raw input contract, not hidden in ambient framework services. |
| D016 | Disabling validation or making validation best-effort. | Creation context and pipeline tests. | Invalid user input must fail loudly before DTO construction. |

## Explicit User Decision Required

Ask the user before implementing any of the following:

- New public attributes, traits, methods, factories, config, or package
  dependencies.
- Any behavior marked `DECISION-BACKLOG`.
- Accepting canonical property names as aliases for mapped external keys
  (`D001`).
- New mapping semantics: dotted paths, numeric paths, class mappers, automatic
  snake/camel conversion, or fallback chains (`D002`, `D003`).
- Runtime collection item inference from PHPDoc or annotations (`D010`).
- Validation attributes, closures, container-aware validation, validator hooks,
  DB-backed rule objects, or ValidationFactory/presence-verifier integration
  (`D007`, `D015`).
- Broad scalar casts, custom casts, global casts, or iterable/collection casts
  beyond the current enum/date-like and `#[ArrayOf]` contracts (`D006`, `D009`).
- Request/model/stdClass creation support or magic `from*` methods (`D008`).
- Union DTO/object hydration, morphable data, or abstract subclass selection
  (`D012`).
- Serialization, transformation, wrapping, partials, Eloquent/Livewire/Inertia,
  TypeScript, Artisan, or cache features (`D013`, `D014`).
- Any ability to skip, disable, or downgrade boundary validation (`D016`).

## Negative Guards Agents May Add

Add these only when they protect a hard Data Forge contract or a nearby ported
scenario. Do not create noisy tests for every skipped Spatie feature.

- `D001`: for a mapped field, the mapped external key is accepted; the
  canonical property name is not an alias. Strict DTOs should reject the raw
  canonical key, while flexible DTOs may ignore it as unknown input.
- `D002`: dotted or numeric mapping syntax must not be interpreted as path
  mapping unless a future decision explicitly adds it.
- `D003`: snake_case, camelCase, studly, or class-level mapper conventions must
  not be applied implicitly.
- `D006`: strings such as `"42"`, `"true"`, or `"1.5"` must not be broadly
  coerced into scalar typed fields; objects must not be guessed into arrays.
- `D009`, `D010`: a nested DTO collection without `#[ArrayOf]` must fail
  inspection or validation instead of guessing item types from PHPDoc alone.
- `D011`: nested DTO hydration must not call a child custom `fromArray()` and
  bypass validation/projection.
- `D012`: DTO/object union targets must surface a package exception rather than
  choosing a branch from payload shape.
- `D016`: no per-call or global setting should allow invalid input to create a
  partial DTO.

Negative guards should use Data Forge APIs and exceptions:
`ValidationException`, `UnknownInputKeyException`, `InspectionException`, or
`InstantiationException` as appropriate.

## Wave Notes: Casts, Name Mappers, Non-Array Creation

This records the cast/name-mapper/normalizer slice (`claudedanger`) so a reviewer
can see which upstream cast tests are covered as guards and which remain open
product decisions.

### Covered as executable guards

- `Casts/BuiltinTypeCastTest.php` (D006): the upstream coercion table is mirrored
  as negative guards. Strings are not coerced to `int`/`bool`
  (`tests/Unit/Hydration/SpatieCastPortTest.php`), and integers/booleans are not
  coerced to `string` while objects are not coerced to `array`
  (`tests/Feature/DtoPipeline/SpatieNegativeGuardTest.php`).
- `Casts/EnumCastTest.php` (declared boundary cast): backed enums hydrate from a
  matching scalar backing value and pass through an existing instance, but the
  case *name*, a non-scalar value, and a numeric string for an int-backed enum
  are all rejected (`SpatieCastPortTest.php`).
- `Casts/DateTimeInterfaceCastTest.php` (declared boundary cast): `#[DateFormat]`
  drives hydration for `Carbon`, `CarbonImmutable`, and `DateTime`; a string that
  does not match the format is rejected (`SpatieCastPortTest.php`).
- `Casts/EnumerableCastTest.php` (D006/D009/D010): a plain `Collection` field
  without `#[ArrayOf]` is not built from array input and does not infer item
  types; only an existing `Collection` instance is accepted
  (`SpatieNegativeGuardTest.php`).
- `Resolvers/NameMappersResolverTest.php` (D003, SKIP): snake_case, StudlyCase,
  and class/config name-mapper conventions are not applied implicitly. Flexible
  DTOs report the missing canonical (camelCase) property; strict DTOs reject the
  unmapped raw key verbatim (`SpatieNegativeGuardTest.php`).

### Intentionally not ported in this slice

- Timezone transformation behavior from `DateTimeInterfaceCastTest.php`
  (`setTimeZone`/`timeZone` shifting wall-clock values across zones). Out of
  scope; `#[DateFormat]` only carries a fixed parse timezone and no
  transformation guarantee is asserted.
- `Casts/UnserializeCastTest.php`, custom casts, and global cast registries:
  remain D006 `DECISION-BACKLOG`. No guard added because there is no current
  surface to protect.
- `Casts/EnumerableCastTest.php` cast-injection mechanics (`withCast`,
  `LazyCollection`, Spatie `DataCollection` item hydration): Spatie product APIs,
  tracked under D006/D009 `SKIP`.

### Non-array creation decision update (D008)

`Normalizers/JsonNormalizerTest.php` shows Spatie accepting a JSON string and
rejecting a bare string, a bare integer, and an integer-only string with
`CannotCreateData`. Data Forge now accepts explicit root-only `fromJson()` and
`fromArrayable()` helpers that normalize to arrays and then call the normal
`fromArray()` pipeline. Invalid JSON or non-string-keyed roots raise
`ValidationException`. `fromStdClass`, request/model normalizers, and magic
`from*` creation remain `SKIP`/decision backlog.

## QA Checklist

Use this checklist after any Spatie-inspired porting slice:

1. Confirm no Spatie dependency was added:
   `rg -n "spatie/laravel-data|Spatie\\\\LaravelData" composer.json composer.lock src tests`
2. Check for accidental Spatie API names in source/tests:
   `rg -n "\\b(Optional|Lazy|DataCollection|PaginatedDataCollection|CursorPaginatedDataCollection|DataCollectionOf|MapInputName|MapName|CreationContextFactory|DataValidationAsserter)\\b" src tests`
3. Check for copied Pest-style upstream tests:
   `rg -n "^(it|test)\\(|expect\\(" tests`
4. Check for Spatie-style creation/collection entrypoints:
   `rg -n "::from\\(|->from\\(|::collect\\(|->collect\\(" src tests`
5. For implementation slices, run the required project checks before handoff:
   `composer phpstan`, `composer test`, then `composer pint`.

Docs-only edits do not require the full check suite, but should still pass
`git diff --check`.
