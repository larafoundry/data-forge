# Spatie Laravel Data Test Port Manifest

This manifest inventories the 72 upstream `*Test.php` files under
`references/spatie-laravel-data/tests`.

Classification is primary per source file. When a source file mixes useful
Data Forge scenarios with unsupported Spatie behavior, the row keeps one
primary classification and records the secondary action in notes.

## PORT

| Source file | Primary scenarios | Classification | Target Data Forge area | Divergence/backlog ID | Notes |
| --- | --- | --- | --- | --- | --- |
| `tests/Support/Validation/RuleNormalizerTest.php` | String rules, rule arguments, key-value arguments, regex containing `|`, multiple and concatenated rules. | `PORT` | `tests/Unit/Validation/DtoRuleBuilderTest.php` | D007 for rule object cases | Port only string/array string normalization edge cases. Laravel rule objects and custom rule objects stay backlog. |

## ADAPT

| Source file | Primary scenarios | Classification | Target Data Forge area | Divergence/backlog ID | Notes |
| --- | --- | --- | --- | --- | --- |
| `tests/Casts/DateTimeInterfaceCastTest.php` | Date-like hydration, explicit formats, invalid formats, timezone-aware inputs, alternative timezone config. | `ADAPT` | `tests/Feature/AsDto`, `tests/Feature/DtoPipeline`, `tests/Unit/Hydration` | - | Adapt to `#[DateFormat]` and Data Forge `ValidationException`/`InspectionException`. Multiple format and nanosecond behavior should be backlog unless already contracted. |
| `tests/Casts/EnumCastTest.php` | Backed enum hydration, invalid enum values, unit enum rejection, wrong input types. | `ADAPT` | `tests/Feature/AsDto`, `tests/Unit/Hydration` | - | Port backed enum success/failure. Unit enum behavior should fail loudly if unsupported by current hydrator. |
| `tests/CreationTest.php` | Constructor/default/readonly creation, nested DTOs, nested collections, constructor failure messages, enums, false values. | `ADAPT` | `tests/Feature/AsDto`, `tests/Feature/DtoPipeline`, `tests/Unit/Hydration`, `tests/Unit/Schema` | D004, D005, D006, D008, D009, D011, D012 | Adapt contract-matching creation and hydration. Skip Optional/Lazy/model/stdClass/magic/custom cast/global cast/paginator/union object behavior; add guards for scalar casts and child `fromArray()` bypass where useful. |
| `tests/DataTest.php` | Data-as-DTO smoke cases using traits/interfaces plus resource behavior. | `ADAPT` | `tests/Feature/AsDto` | D013 for resource cases | Only the DTO boundary idea maps to Data Forge; resource transformation is skipped. |
| `tests/MappingTest.php` | Flat input mapping, nested DTO mapping, mapped collections, alias replacement behavior, class mappers, numeric/dotted mapping. | `ADAPT` | `tests/Unit/Input`, `tests/Feature/DtoPipeline` | D001, D002, D003 | Port flat explicit `#[MapKey]` success and nested error-key scenarios. Canonical aliases, numeric/dotted paths, and class-level mappers become guards/backlog. |
| `tests/RuleInferrers/RequiredRuleInferrerTest.php` | Required/nullable/default interaction, explicit required rules, data collection required handling, undefinable/Optional behavior. | `ADAPT` | `tests/Unit/Validation/DtoRuleBuilderTest.php` | D004, D007 | Adapt required/nullable/default behavior to Data Forge. Rule object and Optional cases do not become current API. |
| `tests/Support/DataAttributesCollectionTest.php` | Reflection attribute collection on classes/properties, inherited attributes, repeated attributes, lookup by parent/interface. | `ADAPT` | `tests/Unit/Schema` | - | Use as schema reflection inspiration only where Data Forge attributes need comparable inspection. |
| `tests/Support/DataClassTest.php` | Class metadata, constructor metadata, defaults on properties/promoted parameters, class-level mapping metadata. | `ADAPT` | `tests/Unit/Schema` | D003, D008 | Adapt constructor/default reflection. Magic methods, global mappers, model/json/stdClass creation are not Data Forge behavior. |
| `tests/Support/DataParameterTest.php` | Constructor parameter metadata: promoted vs non-promoted, default values, mixed/untyped, creation-context parameter. | `ADAPT` | `tests/Unit/Schema` | D008 | Adapt promoted/default/type inspection. Creation-context injection is out of scope. |
| `tests/Support/DataPropertyTest.php` | Property metadata: mapped input/output names, defaults, promoted properties, validation flags, computed/hidden/lazy/cast/transformer attributes. | `ADAPT` | `tests/Unit/Schema` | D004, D005, D007, D013 | Adapt default/promoted/flat mapped input-name ideas. Skip computed, hidden, lazy, custom cast/transformer, Optional, and output mapping behavior. |
| `tests/Support/DataPropertyTypeTest.php` | Type deduction for named, nullable, union, intersection, mixed, DTO, collections, iterable annotations, data collection annotations, keys. | `ADAPT` | `tests/Unit/Schema/TypeSpecTest.php` | D009, D010, D012 | Adapt named/nullable/mixed/basic union inspection where compatible. Runtime collection item inference, Spatie collections, paginator collections, and data/object unions remain unsupported/backlog. |
| `tests/Support/DataReturnTypeTest.php` | Method return type reflection, nullable/union returns, arrays, collections, DataCollection, factory caching. | `ADAPT` | `tests/Unit/Schema` | D008, D009 | Use only for type-reflection ideas that fit current schema inspection. Magic factory return-type resolution and Spatie collection types are out of scope. |
| `tests/Support/Validation/ValidationPathTest.php` | Wildcard/glob expansion for validation paths, trailing globs, deeply nested path expansion. | `ADAPT` | `tests/Unit/Validation`, `tests/Unit/Input` | - | Adapt only if needed for nested collection rule/message paths with Data Forge external-key mapping. |
| `tests/ValidationTest.php` | Scalar validation, custom string rules/messages, mapped validation keys, nested DTOs, nested collections, wildcard paths, defaults, bad payloads. | `ADAPT` | `tests/Feature/DtoPipeline`, `tests/Unit/Validation`, `tests/Unit/Input` | D004, D007, D008, D012, D015, D016 | Main edge-case source. Port Data Forge-compatible validation and external-key errors; skip hooks, DB/route/auth/container refs, validation strategy config, redirect/error bag, morphable data, and unsupported rule surfaces. |

## NEGATIVE-GUARD

| Source file | Primary scenarios | Classification | Target Data Forge area | Divergence/backlog ID | Notes |
| --- | --- | --- | --- | --- | --- |
| `tests/Casts/BuiltinTypeCastTest.php` | Broad builtin casts from strings/objects into scalars/arrays. | `NEGATIVE-GUARD` | `tests/Feature/AsDto`, `tests/Unit/Hydration` | D006 | Add guards that Data Forge does not coerce `"42"` to int, `"true"` to bool, objects to arrays, or similar broad scalar casts. |
| `tests/Casts/EnumerableCastTest.php` | Array-to-collection casts, specified collection types, default collection when type is unclear, Spatie data collections. | `NEGATIVE-GUARD` | `tests/Unit/Hydration`, `tests/Feature/AsDto` | D009, D010 | Guard against guessing iterable item types or silently choosing collection types. Only `Collection` plus explicit `#[ArrayOf]` is supported. |
| `tests/Resolvers/NameMappersResolverTest.php` | Class/global input and output mappers, mapper precedence, int/string/class mapper resolution, default mappers. | `NEGATIVE-GUARD` | `tests/Unit/Input`, `tests/Unit/Schema` | D003 | Add guards only where useful to prove no automatic snake/camel/studly/class-level key mapping exists. |

## DECISION-BACKLOG

| Source file | Primary scenarios | Classification | Target Data Forge area | Divergence/backlog ID | Notes |
| --- | --- | --- | --- | --- | --- |
| `tests/Attributes/Validation/RulesTest.php` | Spatie-style validation attributes, generic `Rule` attribute, invokable rules, `ValidationRule` contract. | `DECISION-BACKLOG` | None | D007 | Only revisit unsupported attribute/invokable/context cases; static `rules()` already accepts explicit Laravel rule objects. |
| `tests/Attributes/Validation/ValidationAttributeTest.php` | Attribute value normalization and string conversion for Spatie-style validation attributes. | `DECISION-BACKLOG` | None | D007 | Requires a broader validation-attribute abstraction than Data Forge currently exposes. |
| `tests/CollectionAttributeWithAnotationsTest.php` | Collection attributes backed by PHPDoc annotations for creation, validation, and transform back. | `DECISION-BACKLOG` | None | D010 | Data Forge requires `#[ArrayOf]` for runtime item type; PHPDoc-only inference is not current contract. |
| `tests/CreationFactoryTest.php` | Creation context toggles: magic methods, validation strategy, property mapping, global casts, collection factory. | `DECISION-BACKLOG` | None | D006, D007, D008, D016 | Would require public factory/creation-context semantics. Do not port as behavior in the first pass. |
| `tests/Normalizers/JsonNormalizerTest.php` | Creation from JSON strings and rejection of non-JSON scalar strings/integers. | `DECISION-BACKLOG` | None | D008 | Lightweight JSON input may be useful later, but current public entry point is `fromArray(array)`. |
| `tests/PipelineTest.php` | Data pipe ordering, replacement, non-existing pipe replacement, payload restructuring before creation. | `DECISION-BACKLOG` | None | D008, D016 | Requires public pipeline extension points. Keep out of the first port. |
| `tests/Support/Annotations/CollectionAnnotationReaderTest.php` | PHPDoc collection item type parsing and caching for collections. | `DECISION-BACKLOG` | None | D010 | Runtime item class must remain explicit via `#[ArrayOf]` unless a new decision changes this. |
| `tests/Support/Annotations/DataIterableAnnotationReaderTest.php` | PHPDoc iterable/data collection parsing, class/method annotations, unions, default PHP types, iterable keys. | `DECISION-BACKLOG` | None | D010 | Useful only if Data Forge starts runtime inference from PHPDoc. |
| `tests/Support/Creation/CreationContextFactoryTest.php` | Config-driven creation context, validation strategy, property mapping, magical creation, optional values, casts. | `DECISION-BACKLOG` | None | D004, D006, D007, D008, D016 | Do not introduce config switches that weaken fail-early `fromArray(array)` behavior. |
| `tests/Support/DataMethodTest.php` | Constructor metadata, magic `from*`/collect methods, parameter matching, return-type matching. | `DECISION-BACKLOG` | None | D008 | Constructor metadata overlaps with schema tests; magic method selection needs a separate public API decision. |
| `tests/Support/Validation/PropertyRulesTest.php` | Mutable rule collection add/replace/remove by type or class. | `DECISION-BACKLOG` | None | D007 | Data Forge keeps static `rules()` normalization only; no mutable rule object collection. |
| `tests/Support/Validation/RuleDenormalizerTest.php` | Denormalizing rule objects, route parameter references, path-aware rule generation. | `DECISION-BACKLOG` | None | D007, D015 | Requires Rule object support and framework reference injection. |

## SKIP

| Source file | Primary scenarios | Classification | Target Data Forge area | Divergence/backlog ID | Notes |
| --- | --- | --- | --- | --- | --- |
| `tests/AppendTest.php` | Appending resource data via methods, closures, and additional-data precedence. | `SKIP` | None | D013 | Resource transformation surface, not boundary hydration. |
| `tests/Attributes/FromAuthenticatedUserPropertyTest.php` | Filling property values from authenticated user properties. | `SKIP` | None | D015 | Auth injection is out of scope. |
| `tests/Attributes/FromAuthenticatedUserTest.php` | Filling current user from default/alternate guards and missing user behavior. | `SKIP` | None | D015 | Auth injection is out of scope. |
| `tests/Attributes/FromContainerPropertyTest.php` | Filling property values from container dependencies and scalar failure. | `SKIP` | None | D015 | Container injection is out of scope. |
| `tests/Attributes/FromContainerTest.php` | Filling dependencies from the container, parameters, and missing dependencies. | `SKIP` | None | D015 | Container injection is out of scope. |
| `tests/Attributes/FromRouteParameterPropertyTest.php` | Filling property values from route parameter properties and scalar failure. | `SKIP` | None | D015 | Route injection is out of scope. |
| `tests/Attributes/FromRouteParameterTest.php` | Filling route parameters and non-request payload behavior. | `SKIP` | None | D015 | Route/request integration is out of scope. |
| `tests/Attributes/Validation/PasswordTest.php` | Laravel password rule attribute variants. | `SKIP` | None | D007 | Spatie-style password validation attributes remain outside current validation-attribute support. |
| `tests/Casts/UnserializeCastTest.php` | Unserialize custom cast, failure handling, silent failure. | `SKIP` | None | D007 | Unsafe/custom cast behavior is outside current cast surface. |
| `tests/Commands/DataStructuresCacheCommandTest.php` | Artisan command for caching data structures and directories. | `SKIP` | None | D014 | Artisan/cache command integration is out of scope. |
| `tests/DataCollectionTest.php` | Spatie DataCollection filtering, array access, pagination, custom collections, LazyCollection, sole/merge behavior. | `SKIP` | None | D009, D013 | Data Forge uses Laravel `Collection` plus `#[ArrayOf]`, not Spatie collection/resource APIs. |
| `tests/DataPipes/FillRouteParameterPropertiesDataPipeTest.php` | Route parameter data pipe injection, custom mapping, replacement controls, scalar failure. | `SKIP` | None | D015 | Route injection/data-pipe behavior is out of scope. |
| `tests/EmptyTest.php` | EmptyData object generation, overwrite, only/except filtering. | `SKIP` | None | D004, D013 | Spatie EmptyData/resource partial behavior has no Data Forge equivalent. |
| `tests/InjectPropertyValuesTest.php` | Arbitrary property injection from external parameters, custom mapping, replacement controls. | `SKIP` | None | D015 | Injection pipeline is out of scope. |
| `tests/LivewireTest.php` | Livewire component integration. | `SKIP` | None | D014 | Livewire integration is explicitly out of scope. |
| `tests/MagicalCreationTest.php` | Magic `from*` and collect methods, creation-context injection, custom collect targets. | `SKIP` | None | D008 | Current creation entry point is only `fromArray(array)`. |
| `tests/Normalizers/FormRequestNormalizerTest.php` | FormRequest normalization, safe data, and request-only behavior. | `SKIP` | None | D008, D015 | Request/FormRequest creation is out of scope. |
| `tests/Normalizers/ModelNormalizerTest.php` | Eloquent model normalization, accessors, loaded relations, relation loading, mapper use. | `SKIP` | None | D008, D014 | Model/Eloquent integration is out of scope. |
| `tests/PartialsTest.php` | Include/exclude/only/except partials, request parsing, lazy partials, collections, circular dependencies. | `SKIP` | None | D005, D013 | Resource partials and Lazy behavior are outside the DTO boundary. |
| `tests/RequestTest.php` | Request validation, response status, authorization, dependency-injected authorize parameters. | `SKIP` | None | D015 | Request integration and authorization are out of scope. |
| `tests/Resolvers/ContextResolverTest.php` | Context resolution from property/class/method and cache reuse. | `SKIP` | None | D015 | Context injection is out of scope. |
| `tests/Resolvers/DataClassFromValidationPayloadResolverTest.php` | Morphable validation payload class resolution, nested morph paths. | `SKIP` | None | D012 | Abstract/morphable DTO hydration is unsupported. |
| `tests/Resolvers/DataMorphClassResolverTest.php` | Morph class resolution by payload properties, mapped names, backed enums, defaults. | `SKIP` | None | D012 | Abstract/morphable DTO hydration is unsupported. |
| `tests/Resolvers/DecoupledPartialResolverTest.php` | Decoupling partial paths with fields, nested fields, all segments, undefined partials. | `SKIP` | None | D013 | Partial resource resolver has no Data Forge surface. |
| `tests/Resolvers/EmptyDataResolverTest.php` | Empty value resolution for no type/basic/collection/Lazy/Optional/union/default/mapped properties. | `SKIP` | None | D004, D005, D013 | EmptyData, Lazy, Optional, and resource defaults are out of scope. |
| `tests/Resolvers/VisibleDataFieldsResolverTest.php` | Hidden/optional/lazy fields, include/exclude/only/except execution, relation-loaded fields, Inertia props. | `SKIP` | None | D005, D013, D014 | Visibility/resource transformation behavior is out of scope. |
| `tests/SerializeableTest.php` | Serialization/unserialization of data objects, collections, context, partials, Lazy, virtual/backed properties. | `SKIP` | None | D005, D013 | Serialization/resource context behavior is outside the boundary pipeline. |
| `tests/Support/Caching/CachedDataConfigTest.php` | Cached data config loading, invalid cache fallback, disabled cache, anonymous castables. | `SKIP` | None | D014 | Spatie config/cache layer is out of scope. |
| `tests/Support/EloquentCasts/DataCollectionEloquentCastTest.php` | Eloquent cast save/load for data collections, nullable/encrypted/abstract/lazy/dirty detection. | `SKIP` | None | D014 | Eloquent casts are explicitly out of scope. |
| `tests/Support/EloquentCasts/DataEloquentCastTest.php` | Eloquent cast save/load for data objects, encrypted values, abstract/morphable data, dirty detection. | `SKIP` | None | D012, D014 | Eloquent casts and morphable data are out of scope. |
| `tests/Support/Lazy/InertiaLazyTest.php` | Inertia LazyProp/OptionalProp version-specific behavior. | `SKIP` | None | D005, D014 | Lazy/Inertia behavior is out of scope. |
| `tests/Support/Partials/PartialTest.php` | Partial parsing and pointer traversal. | `SKIP` | None | D013 | Partial resource helpers have no Data Forge surface. |
| `tests/Support/Transformation/DataContextTest.php` | Data context serialization/deserialization. | `SKIP` | None | D013 | Transformation context is out of scope. |
| `tests/Support/Transformation/TransformationContextFactoryTest.php` | Transform context config, value transformation toggle, mapping, wrapping, custom transformers, depth. | `SKIP` | None | D013 | Transformation/wrapping config is out of scope. |
| `tests/Support/TypeScriptTransformer/DataTypeScriptTransformerTest.php` | TypeScript output, collection/paginator types, mapped names, optional/hidden properties, records. | `SKIP` | None | Project scope | TypeScript generation is outside Data Forge's DTO boundary package. |
| `tests/TransformationTest.php` | Data/resource transformation, transformers, JSON output, hidden values, circular depth, paginated collections. | `SKIP` | None | D009, D013 | Transformation layer is outside current package scope. |
| `tests/Transformers/ArrayableTransformerTest.php` | Arrayable transformer output. | `SKIP` | None | D013 | Transformer layer is out of scope. |
| `tests/Transformers/DateTimeInterfaceTransformerTest.php` | Date output transformation, formats, timezone conversion, leading `!` format. | `SKIP` | None | D013 | Output transformation is out of scope; date hydration is covered via cast tests. |
| `tests/Transformers/EnumTransformerTest.php` | Enum output transformation. | `SKIP` | None | D013 | Output transformation is out of scope; enum hydration is covered via cast tests. |
| `tests/Transformers/SerializeTransformerTest.php` | Custom serializer transformer output. | `SKIP` | None | D013 | Transformer layer is out of scope. |
| `tests/WithDataTest.php` | `WithData` trait on models and requests. | `SKIP` | None | D014, D015 | Model/request integration is out of scope. |
| `tests/WrapTest.php` | Response wrapping, global/default wraps, additional data, data collections, Laravel response behavior. | `SKIP` | None | D013 | Resource/response wrapping is out of scope. |

## Verification Command

Use this command after edits to ensure every upstream test file is mentioned:

```bash
comm -23 \
  <(find references/spatie-laravel-data/tests -name '*Test.php' -type f | sed 's#references/spatie-laravel-data/##' | sort) \
  <(awk -F'|' '/^\| `tests\// { src=$2; gsub(/^[[:space:]`]+|[[:space:]`]+$/, "", src); print src }' docs/spatie-laravel-data-test-port-manifest.md | sort -u)
```

The command should print no output.
