# Agent 08 — Test Coverage Gap Reviewer

## Scope

- Files reviewed:
  - `AGENTS.md`
  - `tests/**`
- Tests reviewed:
  - `tests/Unit/Attributes/MapKeyTest.php`
  - `tests/Unit/Core/ErrorKeyMapperTest.php`
  - `tests/Unit/Core/DtoRuleBuilderTest.php`
  - `tests/Unit/Core/ValidatorTest.php`
  - `tests/Unit/AsDto/NestedDtoTest.php`
  - `tests/Unit/AsDto/AutoCastTest.php`
  - `tests/Unit/AsDto/AsDtoTest.php`
  - `tests/Unit/AsDto/ComplexObjectTest.php`
  - `tests/Unit/BugPoc/*`
  - DTO fixtures under `tests/Unit/**/Objects/*`
- Out of scope:
  - Implementation review of `src/**`
  - Style or formatting issues
  - Documentation accuracy beyond test-contract gaps
  - Fixing or adding tests

## Commands Run

```bash
git status --short
git diff HEAD
vendor/bin/phpunit
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
rg --files tests
rg -n "MapKey|fromArrayStrict|StrictInput|UnknownInputKeyException|ValidationException|InspectionException|rules\(|messages\(|ArrayOf|enum|Date|Carbon|nullable|null|default|fromArray\(" tests
rg -n "collection of collection|Collection<.*Collection|ArrayOf\(.*Collection|array of array|InvalidArrayOfArray|people.*\*|children\.\*|\*\.\*" tests src
rg -n "Rule|rules\(\)|messages\(\)|Invalid.*Message|Closure|ValidationRule|object" tests/Unit/Core tests/Unit/BugPoc tests/Unit/AsDto
rg --pcre2 -n "ValueError|TypeError|InvalidArgumentException|expectException\((?!ValidationException|InspectionException|InstantiationException)" tests
```

Result:

- PHPStan: Pass, no errors.
- PHPUnit: Pass, `189 tests, 450 assertions`.
- Pint/composer validate if run: Not run.

## Verdict

Short conclusion: risky. The existing suite covers many single-boundary contracts well, but the highest-risk composed behaviors are still weakly covered, especially nested MapKey translation combined with collections, strict unknown keys, and nullable nested semantics.

## Findings

### F-01 — Deep MapKey Translation Is Only Covered At One Level

Severity: Medium
Type: Missing coverage
Confidence: High
Root cause: Existing tests prove flat and one-level mapped paths, but not multi-level external-to-canonical translation across nested DTO schemas.
Related symptoms:

- Regressions could translate only the first segment.
- Regressions could double-translate a raw external segment.
- Nested validation errors could expose canonical keys below level one.

Evidence:

- `tests/Unit/Attributes/MapKeyTest.php:18`
- `tests/Unit/AsDto/NestedDtoTest.php:259`
- `tests/Unit/BugPoc/NestedMapKeyDottedRuleBugPocTest.php:13`
- `tests/Unit/Core/ErrorKeyMapperTest.php:43`

Actual behavior:

```php
MappedNestedDtoWithDottedRules::fromArray([
    'person_data' => ['name' => 'Jo'],
]);
```

This verifies one mapped nested root path, for example `person` to `person_data`.

Expected behavior:

Coverage should also pin a path where root, child, and grandchild segments include `#[MapKey]`, for example `account_data.profile_data.first_name`.

Impact:

- A mapper regression below the first nested DTO could pass the current suite.
- Public validation errors could leak canonical names in deeper payloads.

Why this happened:

- Cause: insufficient coverage.

Recommendation:

- Add a three-level DTO fixture with MapKey at multiple levels.
- Assert both successful hydration into canonical properties and failure errors using the full external path.

### F-02 — Collection Item MapKey Behavior Is Under-Covered

Severity: Medium
Type: Missing coverage
Confidence: High
Root cause: Collection tests cover plain DTO items and mapped collection roots, but not normal success/error behavior for item DTOs whose own fields are mapped.
Related symptoms:

- Collection items may hydrate via canonical keys but skip item-level input normalization.
- Item validation errors may report `members.0.firstName` instead of `members.0.first_name`.

Evidence:

- `tests/Unit/AsDto/NestedDtoTest.php:122`
- `tests/Unit/AsDto/NestedDtoTest.php:277`
- `tests/Unit/Attributes/Objects/DtoWithMapKeyNestedCollection.php:20`
- `tests/Unit/BugPoc/NestedStrictErrorKeyBugPocTest.php:79`

Actual behavior:

```php
NestedCollectionDto::fromArray([
    'people' => [
        ['name' => 'John Doe', 'age' => 30, 'email' => 'john@example.com'],
    ],
]);
```

This verifies collection DTO hydration, but the item DTO has no `MapKey`.

Expected behavior:

Coverage should verify collection item DTOs accept external mapped item keys and return public errors using those same item keys.

Impact:

- Regressions in item-level normalization inside `#[ArrayOf]` can pass.
- Public collection errors can drift from the external input contract.

Why this happened:

- Cause: insufficient coverage.

Recommendation:

- Add `Collection<int, MappedMemberDto>` where `MappedMemberDto::$firstName` maps from `first_name`.
- Assert success for `members.0.first_name` and validation error key `members.0.first_name`, not `members.0.firstName`.

### F-03 — Strict Unknown Raw Keys Are Not Tested In Deep Collections

Severity: Medium
Type: Missing coverage
Confidence: High
Root cause: Strict unknown-key preservation is tested for flat, nested, and one-level collection paths, but not under a mapped parent plus nested collection path.
Related symptoms:

- Raw unknown leaf keys can be incorrectly translated when nested under more than one parent.
- Required-field validation can mask strict unknown-key errors in deeper arrays.

Evidence:

- `tests/Unit/BugPoc/AdditionalValidationLogicBugPocTest.php:60`
- `tests/Unit/BugPoc/AdditionalValidationLogicBugPocTest.php:74`
- `tests/Unit/BugPoc/NestedStrictErrorKeyBugPocTest.php:27`
- `tests/Unit/BugPoc/NestedStrictErrorKeyBugPocTest.php:61`

Actual behavior:

```php
NestedStrictCollectionParentPoc::fromArray([
    'members' => [
        ['firstName' => 'Jane'],
    ],
]);
```

This verifies `members.0.firstName`, but the collection is not nested below another mapped DTO boundary.

Expected behavior:

Coverage should assert a path like `team_data.members.0.firstName` preserves the raw unknown leaf while translating only valid parent path segments.

Impact:

- Deep strict-input regressions could produce misleading error paths.
- Unknown raw keys could be double-translated into valid external keys.

Why this happened:

- Cause: insufficient coverage.

Recommendation:

- Add a mapped parent DTO containing a mapped collection of strict mapped children.
- Assert no `team_data.members.0.first_name` key is produced for raw unknown `firstName`.

### F-04 — Nested Custom fromArray Override Is Only Tested For Direct Child DTOs

Severity: Low
Type: Missing coverage
Confidence: High
Root cause: The suite pins that direct nested DTO hydration does not call child `fromArray()`, but not the same contract for collection items or mapped nested fields.
Related symptoms:

- `#[ArrayOf]` hydration could accidentally call item `fromArray()`.
- Mapped nested DTO hydration could diverge from canonical hydrator behavior.

Evidence:

- `tests/Unit/AsDto/NestedDtoTest.php:73`

Actual behavior:

```php
NestedFromArrayOverrideParentDto::fromArray([
    'child' => ['name' => 'Jane'],
]);
```

This confirms the direct child override counter stays at zero.

Expected behavior:

The same contract should be pinned for collection items and mapped nested fields.

Impact:

- Child DTO root-entry customization could accidentally run inside parent hydration.
- Nested collection behavior could stop matching the canonical pipeline contract.

Why this happened:

- Cause: insufficient coverage.

Recommendation:

- Add an override-counting child DTO inside `#[ArrayOf]`.
- Add an override-counting child DTO behind a mapped parent field.
- Assert counters remain zero and canonical hydration output is used.

### F-05 — Nullable Nested DTO Semantics Need A Full Missing/Default/Null Matrix

Severity: Low
Type: Missing coverage
Confidence: High
Root cause: Nullable nested coverage exists for selected cases, but not the complete matrix across default, no-default, explicit null, missing input, collections, and mapped fields.
Related symptoms:

- Missing nullable collection may behave differently from explicit null.
- Mapped nullable nested fields may accept canonical aliases in non-strict mode.
- No-default nullable constructor parameters may regress differently from defaulted nullable parameters.

Evidence:

- `tests/Unit/AsDto/NestedDtoTest.php:87`
- `tests/Unit/AsDto/NestedDtoTest.php:98`
- `tests/Unit/AsDto/NestedDtoTest.php:165`
- `tests/Unit/AsDto/Objects/NullableNestedDto.php:13`
- `tests/Unit/AsDto/Objects/NullableNestedNoDefaultDto.php:30`
- `tests/Unit/AsDto/Objects/NullableNestedCollectionDto.php:52`

Actual behavior:

```php
NullableNestedDto::fromArray(['title' => 'Manager', 'person' => null]);
NullableNestedNoDefaultDto::fromArray(['title' => 'Manager']);
NullableNestedCollectionDto::fromArray(['title' => 'Team', 'people' => null]);
```

These cover important slices, but not the whole semantic matrix.

Expected behavior:

Coverage should explicitly distinguish missing/default/null for nullable nested DTOs and nullable nested collections, including mapped nullable fields.

Impact:

- Default resolution and canonical/external key behavior for nullable nested fields can drift silently.
- Missing and explicit null may become accidentally equivalent or accidentally divergent in the wrong cases.

Why this happened:

- Cause: insufficient coverage.

Recommendation:

- Add a parameterized nullable nested DTO test matrix.
- Include mapped nullable nested DTO and nullable mapped collection variants.

## False Positives Checked

- FP-01: Flat `MapKey` normalization is covered by `MapKeyTest`; not reported as missing.
- FP-02: One-level mapped nested dotted-rule error translation is covered by `NestedDtoTest` and `ErrorKeyMapperTest`; the gap is deeper composition.
- FP-03: Invalid enum/date failures already expect `ValidationException`; the remaining concern is broader invalid scalar/native exception leakage.
- FP-04: Strict unknown keys are covered at root, direct nested DTO, and one-level collection; the gap is mapped/deep collection composition.
- FP-05: Direct nested custom `fromArray()` bypass is covered; the gap is collection and mapped variants.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
|---|---|---|---|
| Deeply nested `MapKey` >= 2 levels | Flat mapped DTO, one-level mapped nested root, one-level ErrorKeyMapper path | No root + child + grandchild mapped path | Three-level DTO with `account_data.profile_data.first_name`; assert success and external validation error path |
| Collection of mapped DTO | Plain collection DTO hydration; mapped collection root; strict mapped child failure path | No normal success/error for item DTO fields with `MapKey` | `members: [['first_name' => 'Ada']]` hydrates `firstName`; invalid item reports `members.0.first_name` |
| Collection-of-collection DTO | Rejection of invalid array-typed `#[ArrayOf]` field | Contract not pinned as supported or explicitly unsupported | If supported, assert `groups.0.1.name`; if unsupported, assert package exception, not native exception |
| Field both mapped and nested DTO | Mapped nested root with unmapped child fields | Child field MapKey inside mapped nested DTO not pinned | `person_data.first_name` hydrates canonical `person->firstName` and validation errors use `person_data.first_name` |
| Strict unknown key inside nested collection | Root and one-level collection strict unknown keys | No mapped parent plus nested collection path | `team_data.members.0.firstName` preserves raw leaf and does not emit `first_name` |
| Double-translation error | Strong strict unknown regression tests | Normal canonical validation errors in deeply mapped paths not pinned against repeated translation | Deep mapped validation failure asserts exact external path and no canonical residue |
| Invalid enum/date/scalar does not leak native exception | Invalid enum and date expect `ValidationException`; Validator type failures covered | Direct DTO invalid scalar hydration boundary is weaker | DTO with `int`, `bool`, `float` receives invalid values; expect `ValidationException`, not `TypeError` |
| Nested custom `fromArray()` override not called | Direct nested child covered | Collection item and mapped nested child not covered | Override counter inside `#[ArrayOf]` and mapped nested DTO remains zero |
| Invalid rule/message shape | Invalid nested rule array item and numeric rule key covered | Rule objects/closures/ValidationRule and invalid `messages()` shape not covered | Static `rules()` with closure/object and `messages()` with non-string entries throw `InspectionException` |
| Nullable nested DTO missing/default/null | Explicit null default, missing no-default, explicit null collection covered | Missing nullable collection, explicit null no-default, mapped nullable variants missing | Parameterized matrix for missing vs null vs valid array, with and without `MapKey` |

## Open Questions

- Should collection-of-collection DTOs be supported, or should the contract explicitly reject them with a package exception?
- Should invalid `messages()` shapes be validated by the schema compiler like invalid `rules()` shapes?
- Should direct `fromArray()` invalid scalar cases always surface as `ValidationException`, even when the failure occurs near hydration?

## Final Assessment

The suite is in good shape for the main single-boundary contracts: flat MapKey normalization, one-level nested validation errors, strict raw unknown-key preservation, enum/date invalid input, and direct nested `fromArray()` bypass. The main risk is combinatorial: MapKey, nested DTOs, collections, strict validation, and nullable defaults are each tested, but not enough in the same scenarios. Adding focused regression tests for those intersections would substantially reduce the chance of contract drift without requiring a broad rewrite of the test suite.
