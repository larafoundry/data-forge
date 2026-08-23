# Agent 05 — Nested DTO, Collections, fromArray Contract

## Scope

- Files reviewed:
  - `src/Core/DtoHydrator.php`
  - `src/Core/Validator.php`
  - `src/Attributes/ArrayOf.php`
  - `src/Core/ContainerHelper.php`
  - Supporting pipeline files: `src/Core/StrictInputValidator.php`, `src/Core/ValidationProjector.php`, `src/Core/RuleScope.php`, `src/Core/DtoSchemaCompiler.php`, `src/Core/ErrorKeyMapper.php`
- Tests reviewed:
  - `tests/Unit/AsDto/NestedDtoTest.php`
  - `tests/Unit/ContainerHelper/ContainerHelperTest.php`
  - `tests/Unit/BugPoc/NestedStrictErrorKeyBugPocTest.php`
  - `tests/Unit/BugPoc/CollectionItemDottedRulesSkippedBugPocTest.php`
  - `tests/Unit/BugPoc/NestedObjectDottedRulesSkippedBugPocTest.php`
  - `tests/Unit/BugPoc/NestedMapKeyDottedRuleBugPocTest.php`
  - Nested DTO / collection fixtures under `tests/Unit/AsDto/Objects`
- Out of scope:
  - Non-nested scalar casting behavior except where it affects nested hydration
  - Broad docs/tooling review outside nested DTO and collection contracts

## Commands Run

```bash
git status --short
git diff HEAD
vendor/bin/phpunit --filter NestedDto
vendor/bin/phpunit --filter ArrayOf
vendor/bin/phpunit --filter ContainerHelper
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
vendor/bin/phpunit --filter NestedStrictErrorKeyBugPocTest
vendor/bin/phpunit --filter CollectionItemDottedRulesSkippedBugPocTest
vendor/bin/phpunit --filter NestedObjectDottedRulesSkippedBugPocTest
vendor/bin/phpunit --filter NestedMapKeyDottedRuleBugPocTest
```

Result:

- PHPStan: passed, no errors.
- PHPUnit:
  - `NestedDto`: passed, 19 tests / 41 assertions.
  - `ArrayOf`: no tests executed for that filter.
  - `ContainerHelper`: passed, 14 tests / 20 assertions.
  - Extra focused regression filters above: passed.
- Pint/composer validate if run: not run.

## Verdict

Needs fix before merge if `fromArrayStrict()` is intended to apply to the whole input payload. The nested architecture is generally clean, but strict propagation and invalid item type precedence have contract gaps.

## Findings

### F-01 — Root `fromArrayStrict()` does not reject unknown nested keys

Severity: High  
Type: Semantic contract issue / Bug  
Confidence: High  
Root cause: Recursive strict preflight replaces root strict mode with `StrictInput` membership for each child DTO.  
Related symptoms:

- Unknown keys inside non-`StrictInput` nested DTOs are ignored even when the root call is `fromArrayStrict()`.
- Unknown keys inside non-`StrictInput` `#[ArrayOf]` collection items are also ignored under root `fromArrayStrict()`.

Evidence:

- `src/Core/StrictInputValidator.php:79`
- `src/Core/StrictInputValidator.php:125`
- `src/Concerns/AsDto.php:40`
- `logs/improvements/strict-input-policy.md:16`

Actual behavior:

```php
NestedDto::fromArrayStrict([
    'title' => 'Manager',
    'person' => [
        'name' => 'John',
        'age' => 30,
        'extra' => true,
    ],
]);

NestedCollectionDto::fromArrayStrict([
    'title' => 'Team',
    'people' => [
        [
            'name' => 'John',
            'age' => 30,
            'extra' => true,
        ],
    ],
]);
```

Both calls succeed and hydrate DTOs. The unknown `person.extra` / `people.0.extra` keys are ignored.

Expected behavior:

`fromArrayStrict()` should reject unknown nested raw input keys if the strict contract means strict validation of the submitted payload, not only the root object:

```php
[
    'person.extra' => ['The extra field is not allowed.'],
]
```

and:

```php
[
    'people.0.extra' => ['The extra field is not allowed.'],
]
```

Impact:

- Callers can believe strict mode closed the full payload while nested unknown input is silently discarded.
- This weakens the raw-input/canonical-input boundary for nested DTO graphs.
- Existing strict nested tests only cover children that implement `StrictInput`, so the root strict propagation gap is not locked down.

Why this happened:

- Classify cause: semantic mismatch / insufficient coverage.
- The recursive strict scanner treats child strictness as a property of the child class only. It does not distinguish "strict because the child class opts in" from "strict because the root caller chose `fromArrayStrict()` for this payload."

Recommendation:

- Decide contract explicitly. If `fromArrayStrict()` is whole-payload strict, carry the active strict flag into nested DTOs and collection items.
- Add tests for root strict unknown keys in a plain nested DTO and a plain `#[ArrayOf]` item.
- If root strict is intentionally root-only, document that clearly because the current `fromArrayStrict()` wording reads broader than the behavior.

### F-02 — Invalid nested item type can be masked by dotted child validation

Severity: Medium  
Type: Semantic contract issue / Missing coverage  
Confidence: High  
Root cause: Projected dotted-rule validation runs before hydration-time structural validation of nested DTO item records.  
Related symptoms:

- A scalar collection item can be reported as `children.0.name` required instead of `children.0` invalid type.
- A scalar nested DTO value can be reported as `child.name` required when parent dotted rules exist.

Evidence:

- `src/Core/Validator.php:247`
- `src/Core/DtoHydrator.php:280`
- `tests/Unit/BugPoc/CollectionItemDottedRulesSkippedBugPocTest.php:55`
- `tests/Unit/BugPoc/NestedObjectDottedRulesSkippedBugPocTest.php:45`

Actual behavior:

```php
CollectionItemDottedRulesParentPocDto::fromArray([
    'children' => [
        'not-a-record',
    ],
]);
```

Observed error:

```php
[
    'children.0.name' => ['validation.required'],
]
```

Expected behavior:

The package should prefer the structural DTO-record failure:

```php
[
    'children.0' => ['The children.0 field has an invalid type.'],
]
```

Impact:

- Error path points at a child property that cannot exist because the item is not a record.
- The caller receives a misleading "missing nested field" failure instead of an invalid item type failure.
- This weakens the stated goal that invalid nested item type errors remain specific and structured.

Why this happened:

- Classify cause: design boundary / insufficient coverage.
- `ValidationProjector` preserves scalar collection items as scalar projected values, then Laravel dotted rules run before `DtoHydrator::hydrateDtoCollection()` can reject non-array items.

Recommendation:

- Add a structural pre-hydration validation step for nested DTO records and collection item records before dotted child rules execute, or suppress child dotted-rule evaluation when the parent/item is not projectable as a DTO record.
- Add focused tests for invalid nested DTO scalar values and invalid collection item scalar values when parent dotted rules are present.

### F-03 — `#[ArrayOf]` misconfiguration is reported as user validation input

Severity: Low  
Type: Semantic contract issue / Design smell  
Confidence: Medium  
Root cause: `ArrayOf` only validates that the class exists at schema compile time; DTO-ness and compatible collection typing are rejected later during hydration as `ValidationException`.  
Related symptoms:

- `#[ArrayOf(stdClass::class)] public Collection $items` reports `items` as an invalid DTO type.
- `#[ArrayOf(BasicDto::class)] public array $people` reports `people` as an invalid collection type.

Evidence:

- `src/Core/DtoSchemaCompiler.php:166`
- `src/Core/DtoHydrator.php:60`
- `src/Core/DtoHydrator.php:263`
- `src/Attributes/ArrayOf.php:15`
- `tests/Unit/AsDto/NestedDtoTest.php:317`

Actual behavior:

```php
final class BadArrayOfDto
{
    use AsDto;

    public function __construct(
        #[ArrayOf(stdClass::class)]
        public readonly Collection $items,
    ) {}
}

BadArrayOfDto::fromArray(['items' => [[]]]);
```

Observed error:

```php
[
    'items' => ['The items field has an invalid DTO type.'],
]
```

Expected behavior:

DTO declaration problems should surface as inspection/configuration errors, not caller input validation errors.

Impact:

- Package users may misdiagnose DTO declaration bugs as bad request payloads.
- Exception contract becomes less crisp around "invalid user input" vs "inspection/configuration problem."

Why this happened:

- Classify cause: design boundary.
- Some `ArrayOf` contract checks live in the hydrator rather than the schema compiler / inspector layer where DTO metadata is established.

Recommendation:

- Move `ArrayOf` item DTO validation and compatible `Collection` type validation into schema inspection where possible.
- Keep hydration errors for payload shape problems only.
- Add tests asserting exception class for invalid `ArrayOf` declarations.

## False Positives Checked

- FP-01: Custom child `fromArray()` override not being called for nested DTOs is intentional. It is documented in `README.md:168` and covered by `tests/Unit/AsDto/NestedDtoTest.php:73`.
- FP-02: Nullable nested DTO with explicit null works via normal type validation and `ContainerHelper` instantiation.
- FP-03: Missing nullable nested DTO without default becomes null and is covered by `tests/Unit/AsDto/NestedDtoTest.php:98`.
- FP-04: Nullable nested collection with explicit null works and is covered by `tests/Unit/AsDto/NestedDtoTest.php:165`.
- FP-05: Strict nested DTOs that implement `StrictInput` do reject unknown nested keys and preserve raw error paths; `NestedStrictErrorKeyBugPocTest` passes.
- FP-06: Already hydrated nested DTO objects and collection item DTO objects still participate in parent dotted validation; focused regression tests pass.
- FP-07: Constructor defaults, readonly promoted constructor properties, and nullable constructor params are handled by `ContainerHelper` for nested creation paths.
- FP-08: Collection-of-collection is not currently supported directly; `#[ArrayOf]` is constrained to DTO item records.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
| --- | --- | --- | --- |
| Root `fromArrayStrict()` rejects unknown keys inside plain nested DTOs | Root unknown key and `StrictInput` child unknown key tests exist | No test for root strict propagation into non-`StrictInput` child | `NestedDto::fromArrayStrict()` with `person.extra` should fail |
| Root `fromArrayStrict()` rejects unknown keys inside plain collection items | Strict child collection tests exist | No test for root strict propagation into non-`StrictInput` `#[ArrayOf]` item | `NestedCollectionDto::fromArrayStrict()` with `people.0.extra` should fail |
| Invalid collection item scalar with parent dotted rules | Invalid scalar item without dotted parent rule is manually verified by existing behavior | Dotted rules can mask structural type error | Parent rule `children.*.name`, input `children => ['bad']`, expect `children.0` invalid type |
| Invalid nested DTO scalar with parent dotted rules | Dotted rule validations for DTO objects/arrays exist | Dotted rules can mask structural type error | Parent rule `child.name`, input `child => 'bad'`, expect `child` invalid type |
| `ArrayOf` item class must be a DTO | Runtime invalid DTO type path exists | Exception class for DTO declaration problem is not locked | `#[ArrayOf(stdClass::class)] Collection $items` should throw inspection/config exception if adopted |
| `ArrayOf` requires a `Collection` runtime type | Existing test only asserts `people` validation key | Exception class / declaration-vs-input semantics are not locked | `#[ArrayOf(BasicDto::class)] array $people` should assert intended exception class |

## Open Questions

- Is `fromArrayStrict()` intended to be strict over the entire nested payload, or only the root DTO plus nested DTO classes that implement `StrictInput`?
- Should invalid `#[ArrayOf]` declarations be `InspectionException` consistently, or is current `ValidationException` considered part of the public contract?
- Should structural parent/item type errors take precedence over dotted child validation rules in all cases?

## Final Assessment

The nested DTO and collection architecture is mostly sound: hydration goes through the canonical validator/hydrator pipeline, child `fromArray()` overrides are intentionally root-only, mapped nested errors are translated at the root boundary, and nullable/default constructor behavior is broadly correct. The main risk is semantic rather than mechanical: strict mode currently does not mean strict nested payloads unless child DTO classes opt in, and dotted child rules can obscure invalid nested record shapes. I would fix or explicitly document those contracts before merge.
