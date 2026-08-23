# Kế hoạch port test từ `spatie/laravel-data`

## Mục tiêu

Mục tiêu của tài liệu này là biến test suite lớn của
`references/spatie-laravel-data` thành một nguồn edge case có kiểm soát cho
Data Forge.

Không coi Spatie là compatibility target. Spatie là nguồn scenario để tìm lỗi
sớm, còn assertion cuối cùng phải theo contract của Data Forge:

`raw input -> strict preflight -> normalize to canonical keys -> validate raw/projected shape -> hydrate/cast -> validate typed data -> instantiate DTO`

Trong phase planning này chỉ sửa tài liệu planning. Không sửa code, không port
test, không thêm dependency.

## Nguồn tham chiếu

- Local contract: `AGENTS.md`, `docs/DATA.md`, `docs/open-source-test-reuse.md`
- Local source suite: `references/spatie-laravel-data`
- Spatie docs:
  - Validation: https://spatie.be/docs/laravel-data/v4/validation/introduction
  - Validation nesting: https://spatie.be/docs/laravel-data/v4/validation/nesting-data
  - Mapping rules: https://spatie.be/docs/laravel-data/v4/advanced-usage/mapping-rules
  - Creating data objects: https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/creating-a-data-object
  - Nesting: https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/nesting
  - Collections: https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/collections
  - Casts: https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/casts
  - License: https://raw.githubusercontent.com/spatie/laravel-data/main/LICENSE.md

Source snapshot lúc lập plan:

- Spatie local suite có 72 file `*Test.php`.
- Spatie local suite có khoảng 716 Pest test cases.
- Data Forge hiện có 36 file `*Test.php`.
- Data Forge hiện có khoảng 226 PHPUnit test methods.

Spatie dùng MIT license, nên license không phải trở ngại chính. Tuy vậy, mặc
định vẫn nên rewrite scenario theo API Data Forge. Nếu copy đáng kể fixture,
helper, hoặc assertion gần nguyên văn, phải giữ provenance rõ trong commit hoặc
comment test.

## Quyết định đã lock

- Spatie là nguồn edge-case only, không phải mục tiêu tương thích API/behavior.
- Mapping giữ flat explicit `#[MapKey]`.
- Mapped external key là accepted input key; canonical property name không là
  alias.
- Không support dotted path mapping, numeric path mapping, class-level mapper,
  snake/camel mapper, hoặc fallback giữa original/mapped key.
- Validation rule surface hỗ trợ pipe string, flat array of strings, và explicit
  Laravel rule objects trong static `rules()`: custom rules ưu tiên
  `ValidationRule`; legacy `Rule` và `Stringable` được giữ cho Laravel
  compatibility.
- Validation attributes, closures, DB-backed rule objects, container/context
  injection, validator hooks, validation strategy config, stop-on-first-failure,
  redirect, error bag là out of scope.
- Creation hiện tại có `fromArray(array)`, root-only `fromJson(string)`, và
  root-only `fromArrayable(Arrayable)`.
- stdClass lightweight creation có thể vào backlog, nhưng không implement trong
  đợt này.
- Request/model creation, normalizer pipeline, magic `from*` methods là out of
  scope hiện tại.
- Casting giữ enum/date-like hiện có.
- Không broad scalar casts, global casts, custom casts, iterable item casts.
- Nested collection giữ `Illuminate\Support\Collection` + `#[ArrayOf]`.
- Không port Spatie `DataCollection`, paginator collections, hoặc annotation
  `array<Dto>` support trong đợt đầu.

## Nhãn phân loại test

Dùng các nhãn này khi agent đọc từng file/case Spatie:

- `PORT`: Scenario khớp contract Data Forge, chỉ rewrite test sang PHPUnit/API
  Data Forge.
- `ADAPT`: Ý tưởng tốt nhưng Spatie assertion/API khác; rewrite fixture và
  assertion theo `docs/DATA.md`.
- `NEGATIVE-GUARD`: Spatie support behavior mà Data Forge cố tình không support;
  thêm test bảo vệ để Data Forge fail loud/ignore đúng contract.
- `DECISION-BACKLOG`: Behavior có thể hữu ích nhưng cần user quyết định trước
  khi thành public API.
- `SKIP`: Ngoài mục tiêu package nhỏ, hoặc gắn framework integration nặng.

Không có agent nào được tự đổi `DECISION-BACKLOG` thành behavior mới trong khi
port test.

## Inventory và phân loại nguồn

### Nhóm ưu tiên cao

| Source | Classification | Target Data Forge area | Ghi chú |
| --- | --- | --- | --- |
| `tests/ValidationTest.php` | `PORT`/`ADAPT`/`NEGATIVE-GUARD` | `tests/Feature/DtoPipeline`, `tests/Unit/Validation`, `tests/Unit/Input` | Nguồn chính cho nested validation, nested collections, nullable/default, manual messages, wildcard paths, mapping. Không port validator hooks, DB constraints, route/user/container references, morphable data. |
| `tests/MappingTest.php` | `ADAPT`/`NEGATIVE-GUARD` | `tests/Unit/Input`, `tests/Feature/DtoPipeline` | Chỉ lấy flat explicit mapping và nested collection mapping concept. Dotted/numeric/class mapper/original fallback thành negative guard hoặc backlog. |
| `tests/CreationTest.php` | `ADAPT`/`NEGATIVE-GUARD` | `tests/Feature/AsDto`, `tests/Feature/DtoPipeline`, `tests/Unit/Hydration` | Lấy constructor/default/readonly/nested hydration/constructor failure. Bỏ Lazy, Optional, model, request, magic methods, custom casts, computed/virtual, paginator collection. |
| `tests/CreationFactoryTest.php` | `DECISION-BACKLOG`/`SKIP` | None | Spatie creation factory/validation strategy không thuộc alpha API của Data Forge. Chỉ quay lại nếu mở một creation pipeline riêng sau này. |
| `tests/MagicalCreationTest.php` | `SKIP` | None | Magic `from*` methods không thuộc `fromArray(array)` contract. |
| `tests/Casts/EnumCastTest.php` | `ADAPT` | `tests/Feature/AsDto`, `tests/Unit/Hydration` | Backed enum success/failure. Unit enum unsupported nếu contract hiện tại không hydrate được. |
| `tests/Casts/DateTimeInterfaceCastTest.php` | `ADAPT` | `tests/Feature/AsDto`, `tests/Unit/Hydration` | Date-like values, explicit format, invalid date behavior. Multiple formats/timezone/nanosecond cases vào backlog nếu chưa có contract. |
| `tests/RuleInferrers/RequiredRuleInferrerTest.php` | `ADAPT` | `tests/Unit/Validation/DtoRuleBuilderTest.php` | Lấy required/nullable/default interaction theo Data Forge. Unsupported rule surfaces remain backlog. |
| `tests/Support/Validation/RuleNormalizerTest.php` | `PORT`/`DECISION-BACKLOG` | `tests/Unit/Validation/DtoRuleBuilderTest.php` | Port string rules, concatenated pipe rules, regex containing `|`, and explicit Laravel rule objects. Broader denormalization/context cases remain backlog. |
| `tests/Support/Validation/RuleDenormalizerTest.php` | `DECISION-BACKLOG` | None | Data Forge hiện không có rule object denormalizer/mutable rule layer. Chỉ quay lại nếu mở rộng validation API. |
| `tests/Support/Validation/PropertyRulesTest.php` | `DECISION-BACKLOG` | None | Spatie mutable rule collection khác `DtoRuleBuilder` hiện tại. Dùng làm ý tưởng nếu refactor rules layer. |
| `tests/Support/Validation/ValidationPathTest.php` | `ADAPT` | `tests/Unit/Validation`, `tests/Unit/Input` | Lấy wildcard path expansion concept nếu cần cho nested collection messages. |

### Nhóm medium, dùng để đào edge cases

| Source | Classification | Target Data Forge area | Ghi chú |
| --- | --- | --- | --- |
| `tests/Support/DataClassTest.php` | `ADAPT` | `tests/Unit/Schema` | Schema/reflection concepts. Bỏ Spatie-specific data class features. |
| `tests/Support/DataPropertyTest.php` | `ADAPT`/`SKIP` | `tests/Unit/Schema` | Lấy default value, promoted property, mapped input name concepts. Bỏ computed, virtual, hidden, lazy, transformers, casts. |
| `tests/Support/DataPropertyTypeTest.php` | `ADAPT`/`DECISION-BACKLOG` | `tests/Unit/Schema/TypeSpecTest.php` | Lấy named/nullable/union/intersection/mixed cases. Data union/object union, data collection annotations, lazy/optional/paginator types backlog hoặc skip. |
| `tests/Support/DataParameterTest.php` | `ADAPT` | `tests/Unit/Schema` | Constructor parameter metadata nếu khớp compiler hiện tại. |
| `tests/Support/DataReturnTypeTest.php` | `ADAPT`/`SKIP` | `tests/Unit/Schema` | Chỉ lấy type deduction hữu ích. Bỏ magic method return behavior nếu không có API. |
| `tests/Support/DataMethodTest.php` | `SKIP`/`DECISION-BACKLOG` | None | Gắn magic creation/custom methods; chỉ quay lại nếu mở roadmap creation API. |
| `tests/Support/DataAttributesCollectionTest.php` | `ADAPT`/`SKIP` | `tests/Unit/Schema` | Chỉ lấy repeated/filtered attribute inspection nếu Data Forge có nhu cầu. |
| `tests/Support/Annotations/CollectionAnnotationReaderTest.php` | `DECISION-BACKLOG` | None | Data Forge hiện yêu cầu `#[ArrayOf]`; annotation `array<Dto>` chưa support. |
| `tests/Support/Annotations/DataIterableAnnotationReaderTest.php` | `DECISION-BACKLOG` | None | Tương tự: annotation/PHPDoc iterable support chưa là contract. |
| `tests/Attributes/Validation/RulesTest.php` | `ADAPT`/`DECISION-BACKLOG` | `tests/Unit/Validation` | Chỉ lấy string-rule concept nếu tương đương. Attribute rule objects backlog. |
| `tests/Attributes/Validation/ValidationAttributeTest.php` | `DECISION-BACKLOG` | None | Spatie-style validation attributes rộng hơn Data Forge attributes hiện tại. |
| `tests/Attributes/Validation/PasswordTest.php` | `SKIP` | None | Spatie-style password validation attributes remain outside current validation-attribute support. |
| `tests/Casts/BuiltinTypeCastTest.php` | `NEGATIVE-GUARD` | `tests/Feature/AsDto`, `tests/Unit/Hydration` | Spatie broad scalar cast trái contract. Dùng để chứng minh string `"42"` không thành int, `"true"` không thành bool, object không thành array. |
| `tests/Casts/EnumerableCastTest.php` | `NEGATIVE-GUARD`/`DECISION-BACKLOG` | `tests/Unit/Hydration` | Chỉ guard không tự đoán iterable/collection item type. |
| `tests/Casts/UnserializeCastTest.php` | `SKIP` | None | Custom unsafe cast không thuộc scope. |
| `tests/CollectionAttributeWithAnotationsTest.php` | `DECISION-BACKLOG` | None | Annotation collection support chưa thuộc contract. |

### Nhóm skip mặc định

Các file dưới đây không nên port trong đợt đầu. Chỉ quay lại nếu user mở rộng
scope package.

| Source | Classification | Lý do |
| --- | --- | --- |
| `tests/AppendTest.php` | `SKIP` | Resource/transform append behavior, không phải boundary hydration. |
| `tests/PartialsTest.php` | `SKIP` | Include/exclude/partial resource behavior. |
| `tests/TransformationTest.php` | `SKIP` | Transform to array/resource, không thuộc Data Forge hiện tại. |
| `tests/Transformers/*` | `SKIP` | Transformer layer ngoài scope. |
| `tests/SerializeableTest.php` | `SKIP` | Serialization behavior ngoài scope DTO boundary. |
| `tests/DataCollectionTest.php` | `SKIP`/`DECISION-BACKLOG` | Spatie collection class/pagination APIs. |
| `tests/DataTest.php` | `SKIP`/`ADAPT` | Phần serialization/resource skip; chỉ lấy minimal creation edge case nếu trùng contract. |
| `tests/EmptyTest.php` | `SKIP` | EmptyData riêng của Spatie. |
| `tests/WrapTest.php` | `SKIP` | Resource wrapping. |
| `tests/WithDataTest.php` | `SKIP` | Trait integration với model/resource. |
| `tests/LivewireTest.php` | `SKIP` | Livewire integration. |
| `tests/RequestTest.php` | `SKIP` | Request integration. |
| `tests/Normalizers/*` | `DECISION-BACKLOG`/`SKIP` | Request/model/json/stdClass/normalizer creation chưa support. |
| `tests/Commands/DataStructuresCacheCommandTest.php` | `SKIP` | Artisan command/cache command ngoài scope. |
| `tests/DataPipes/FillRouteParameterPropertiesDataPipeTest.php` | `SKIP` | Route parameter injection ngoài scope. |
| `tests/Attributes/FromAuthenticatedUser*` | `SKIP` | Auth injection ngoài scope. |
| `tests/Attributes/FromContainer*` | `SKIP` | Container injection ngoài scope. |
| `tests/Attributes/FromRouteParameter*` | `SKIP` | Route injection ngoài scope. |
| `tests/InjectPropertyValuesTest.php` | `SKIP` | Property injection pipeline ngoài scope. |
| `tests/PipelineTest.php` | `SKIP`/`DECISION-BACKLOG` | Spatie pipeline extension points không có trong Data Forge. |
| `tests/Resolvers/VisibleDataFieldsResolverTest.php` | `SKIP` | Include/exclude visible fields. |
| `tests/Resolvers/DecoupledPartialResolverTest.php` | `SKIP` | Partial resource behavior. |
| `tests/Resolvers/ContextResolverTest.php` | `SKIP` | Context injection. |
| `tests/Resolvers/DataMorphClassResolverTest.php` | `SKIP` | Morph/abstract data behavior. |
| `tests/Resolvers/DataClassFromValidationPayloadResolverTest.php` | `SKIP` | Morphable validation payload behavior. |
| `tests/Resolvers/EmptyDataResolverTest.php` | `SKIP` | EmptyData behavior. |
| `tests/Resolvers/NameMappersResolverTest.php` | `NEGATIVE-GUARD`/`SKIP` | Class-level mapper not supported; add guards only where useful. |
| `tests/Support/Caching/CachedDataConfigTest.php` | `SKIP` | Spatie config/cache layer. |
| `tests/Support/Creation/CreationContextFactoryTest.php` | `SKIP` | Spatie creation context/magic factory. |
| `tests/Support/EloquentCasts/*` | `SKIP` | Eloquent casts explicitly out of scope. |
| `tests/Support/Lazy/InertiaLazyTest.php` | `SKIP` | Lazy/Inertia behavior explicitly out of scope. |
| `tests/Support/Partials/PartialTest.php` | `SKIP` | Resource partials. |
| `tests/Support/Transformation/*` | `SKIP` | Transformation context. |
| `tests/Support/TypeScriptTransformer/*` | `SKIP` | TypeScript generation explicitly out of scope. |

## Behavior divergence register

| ID | Source behavior in Spatie | Data Forge decision | Required action |
| --- | --- | --- | --- |
| D001 | Mapped and original names may both appear in creation contexts. | Mapped external key is the only accepted input key for mapped fields. Canonical key is not alias. | Add `NEGATIVE-GUARD` tests for canonical alias rejection/ignore based on strictness. |
| D002 | `MapInputName('nested.something')`, numeric paths, and mixed path mapping. | `#[MapKey]` is flat only. | Keep as `DECISION-BACKLOG`; add guard if someone tries dotted/numeric mapping behavior. |
| D003 | Class-level mappers like snake/camel/studly. | No implicit key formatters. | `SKIP`; optional guard for no automatic snake/camel conversion. |
| D004 | `Optional` type changes required/sometimes behavior. | No `Optional`; missing nullable without default follows Data Forge contract. | `SKIP`; do not emulate `sometimes`. |
| D005 | Lazy properties and Inertia deferred/lazy values. | Explicitly unsupported. | `SKIP`. |
| D006 | Broad builtin casts convert strings/objects into scalar/array types. | No broad scalar auto-casting. | Add `NEGATIVE-GUARD` tests for scalar strictness. |
| D007 | Rule objects, Spatie-style validation attributes, closures, dependency injection. | Explicit Laravel rule objects in static `rules()` are supported: prefer `ValidationRule`; legacy `Rule` and `Stringable` are compatibility paths. Spatie-style attributes/closures/DB/context remain unsupported. | Keep remainders as `DECISION-BACKLOG`; do not add dependency/API without a new decision. |
| D008 | Request/model/stdClass/json/Arrayable normalizers. | `fromJson()` and `fromArrayable()` are explicit root-only helpers. | Keep `fromStdClass`, request/model, and magic normalizers as `DECISION-BACKLOG`/`SKIP`. |
| D009 | Spatie `DataCollection`, paginated/cursor collections, custom collection APIs. | Only Laravel `Collection` plus `#[ArrayOf]`. | `SKIP` or backlog for `array<Dto>` only if user asks. |
| D010 | Collection item type can be inferred from annotations/PHPDoc. | Data Forge requires explicit `#[ArrayOf]`. PHPDoc helps static tools only. | `DECISION-BACKLOG`; do not infer runtime item class from docs. |
| D011 | Nested child `from()`/custom casts can own hydration. | Nested DTOs use canonical validator/hydrator pipeline; custom root `fromArray()` overrides are root entrypoints only. | Add regression/guard where valuable. |
| D012 | Abstract/morphable data chooses subclass from payload. | Union DTO/object hydration targets unsupported. | `SKIP`; add inspection failure tests only if nearby. |
| D013 | Resource transformation, wrapping, append, partials. | Out of boundary package scope. | `SKIP`. |
| D014 | Eloquent casting, Livewire, Inertia, Artisan commands. | Explicitly unwanted. | `SKIP`. |
| D015 | DB validation constraints and route/auth/container references. | Framework integration too broad. | `SKIP` until user explicitly requests. |
| D016 | Validation can be disabled/configured globally. | Data Forge must fail early at boundary. | `NEGATIVE-GUARD` only if a future config is proposed. |

## Agent split for parallel execution

Use at most 4 agents. Each agent should work on a small, independent slice and
avoid changing shared helpers unless the slice proves duplication is painful.

### Agent 1: Inventory, dedupe, and tracker

Deliverables:

- Create a temporary local checklist or issue-style manifest before porting.
- Mark each Spatie file/case with `PORT`, `ADAPT`, `NEGATIVE-GUARD`,
  `DECISION-BACKLOG`, or `SKIP`.
- Dedupe against existing Data Forge tests before assigning work.
- Keep provenance notes for any scenario copied closely.

Agent 1 may decide:

- Scenario names.
- Test grouping.
- Whether an existing Data Forge test already covers a Spatie scenario.

Agent 1 must escalate:

- Any proposed new public API.
- Any scenario that conflicts with `docs/DATA.md`.
- Any dependency addition.

### Agent 2: Validation and mapping

Primary sources:

- `tests/ValidationTest.php`
- `tests/MappingTest.php`
- `tests/Support/Validation/*`
- `tests/RuleInferrers/RequiredRuleInferrerTest.php`

Target areas:

- `tests/Feature/DtoPipeline`
- `tests/Unit/Validation`
- `tests/Unit/Input`

Port first:

- Required/string/int/float/bool/array validation where Data Forge contract
  matches.
- Nested DTO validation paths.
- Nullable nested DTO validation.
- Nested collection validation paths.
- Manual custom messages for nested DTOs and nested collections.
- Wildcard collection paths where Laravel validator behavior matters.
- Mapped input key errors rendered back to external keys.

Negative guards:

- Original canonical key not accepted as alias for mapped field.
- Dotted/numeric/class-level mapping not implemented.
- Spatie-style validation hook/context/dependency injection not accepted.

### Agent 3: Hydration, creation, and casts

Primary sources:

- `tests/CreationTest.php`
- `tests/Casts/EnumCastTest.php`
- `tests/Casts/DateTimeInterfaceCastTest.php`
- `tests/Casts/BuiltinTypeCastTest.php`
- `tests/Casts/EnumerableCastTest.php`

Target areas:

- `tests/Feature/AsDto`
- `tests/Feature/DtoPipeline`
- `tests/Unit/Hydration`
- `tests/Unit/Schema`

Port first:

- Constructor defaults and readonly properties.
- Nested DTO hydration from arrays.
- Nested collection hydration with `Collection` + `#[ArrayOf]`.
- Backed enum success and invalid value failure.
- Date-like success/failure including `#[DateFormat]`.
- Constructor/instantiation failure messages where Data Forge owns behavior.

Negative guards:

- String scalar values are not broadly cast to int/bool/float/string.
- Associative arrays are not accepted for list-like `#[ArrayOf]` collections.
- Untyped collection item classes are not guessed.
- Nested child custom `fromArray()` does not bypass the pipeline.

### Agent 4: Unsupported surface, backlog, and QA

Primary sources:

- All `SKIP` and `DECISION-BACKLOG` groups.
- Spatie docs and license.
- `docs/DATA.md`

Deliverables:

- Keep a backlog list of unsupported-but-interesting behavior.
- Ensure no skipped scenario silently becomes a new feature.
- Check for accidental Spatie dependency imports.
- Run final checks after porting:
  - `composer phpstan`
  - `composer test`
  - `composer pint`

Agent 4 must add a required log file only when implementation confirms a bug or
intentional improvement:

- Bug: `logs/bugs/<short-kebab-name>.md`
- Improvement: `logs/improvements/<short-kebab-name>.md`

Do not add log files for planning-only documentation.

## Suggested test layout for later implementation

Keep Spatie-inspired tests focused by behavior, not by upstream file name.

Recommended new or expanded files:

- `tests/Feature/DtoPipeline/SpatieNestedValidationPortTest.php`
- `tests/Feature/DtoPipeline/SpatieMappingPortTest.php`
- `tests/Feature/DtoPipeline/SpatieCreationHydrationPortTest.php`
- `tests/Unit/Hydration/SpatieCastPortTest.php`
- `tests/Unit/Validation/SpatieRulePortTest.php`
- `tests/Unit/Input/SpatieMappingGuardTest.php`

Rules:

- Use PHPUnit style, matching the current Data Forge suite.
- Prefer small local fixture classes inside the test file unless sharing removes
  real duplication.
- Rewrite Pest expectations into explicit PHPUnit assertions.
- Assert Data Forge exceptions:
  - invalid user input: `ValidationException`
  - unknown strict input: `UnknownInputKeyException`
  - inspection/configuration problems: `InspectionException`
  - instantiation failures: `InstantiationException`
- Assert external input keys for root validation errors.
- Assert raw key paths for strict unknown-key errors.
- Assert canonical keys only for successful validated data/DTO construction.

## Execution phases

### Phase 0: Build manifest

- Agent 1 creates a case manifest from all 72 Spatie test files.
- Each row records upstream file, short scenario, classification, target area,
  and whether an existing Data Forge test already covers it.
- No source/test implementation yet.

Acceptance criteria:

- Every Spatie `*Test.php` file is represented.
- Every `DECISION-BACKLOG` item references a divergence ID from this doc or adds
  a new one.

### Phase 1: Port high-confidence validation cases

- Agent 2 ports nested DTO, nested collection, messages, mapped key, and wildcard
  path cases.
- Keep each commit/slice small enough to run focused tests.

Acceptance criteria:

- Focused validation/input tests pass.
- Error keys follow raw-vs-canonical rules from `AGENTS.md`.
- Only explicit Laravel rule objects are introduced; closures, DB-backed rules,
  and validator hook APIs remain unsupported.

### Phase 2: Port creation/hydration/cast cases

- Agent 3 ports constructor/default/nested/enum/date cases.
- Add negative guards for broad scalar casts and untyped collection guessing.

Acceptance criteria:

- Focused hydration/schema tests pass.
- No normalizer, custom cast, global cast, or magic creation API is introduced.

### Phase 3: Backlog and negative guards

- Agent 4 reviews skipped/backlog behavior against user decisions.
- Add negative guards only where they protect a Data Forge hard contract.
- Do not add tests for every skipped feature if that would create noise.

Acceptance criteria:

- Backlog is explicit.
- Negative guards document intentional non-support.
- No test asserts Spatie compatibility unless Data Forge contract already says
  the same thing.

### Phase 4: Full verification

Before commit or handoff:

1. Run `composer phpstan`.
2. Run `composer test`.
3. Run `composer pint` last.
4. Fix all failures.

If a ported test exposes a confirmed bug, add a focused bug log before fixing.
If a port requires intentional new behavior, stop and ask for a decision before
implementation unless the behavior is already accepted in this doc.

## What agents may decide themselves

Agents may decide:

- Test method names.
- Fixture class names.
- Whether to place a scenario in unit or feature tests when behavior remains
  clear.
- Whether to combine two very similar scenarios into one parameterized-style
  PHPUnit data provider.
- Whether an upstream scenario is already covered by existing Data Forge tests.
- Whether to port a case as positive assertion or negative guard when this doc
  already defines the behavior.

Agents must not decide:

- New public attributes, traits, methods, factories, config, or dependencies.
- New key mapping semantics.
- New cast semantics beyond enum/date-like behavior.
- Support for Spatie `Optional`, `Lazy`, `DataCollection`, normalizers, request
  integration, model integration, TypeScript, Livewire, Inertia, Artisan
  commands, or Eloquent casts.
- Treating canonical property names as aliases for mapped raw input keys.
- Runtime collection item inference from PHPDoc.
- Union DTO/object hydration.
- Swallowing validation/inspection/instantiation failures.

## Minimal first batch

If only one small batch can be done first, choose these:

1. Nested DTO required field error path inspired by
   `tests/ValidationTest.php`.
2. Nested collection item required field error path inspired by
   `tests/ValidationTest.php`.
3. Mapped nested validation error renders external key inspired by
   `tests/MappingTest.php` and `tests/ValidationTest.php`.
4. Backed enum invalid input inspired by `tests/Casts/EnumCastTest.php`.
5. Date format invalid input inspired by
   `tests/Casts/DateTimeInterfaceCastTest.php`.
6. Negative guard for broad scalar casting inspired by
   `tests/Casts/BuiltinTypeCastTest.php`.
7. Negative guard for mapped canonical alias inspired by
   `tests/MappingTest.php`.

This first batch gives the highest value without opening new product surface.
