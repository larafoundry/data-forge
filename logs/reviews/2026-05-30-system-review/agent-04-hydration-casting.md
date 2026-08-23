# Agent 04 — Hydration, Casting, Enum, Date

## Scope

- Files reviewed:
  - `src/Core/DtoHydrator.php`
  - `src/Core/DtoRuleBuilder.php`
  - `src/Core/Validator.php`
  - `src/Core/ValidationProjector.php`
  - `src/Core/TypeSpec.php`
  - `src/Attributes/DateFormat.php`
  - `src/Attributes/Min.php`
  - `src/Attributes/Max.php`
  - `src/Attributes/StringLength.php`
  - `README.md`
  - related bug/improvement logs under `logs/bugs` and `logs/improvements`
- Tests reviewed:
  - `tests/Unit/AsDto/AutoCastTest.php`
  - `tests/Unit/BugPoc/CoreValidationBugPocTest.php`
  - `tests/Unit/BugPoc/DataContractBugPocTest.php`
  - `tests/Unit/BugPoc/ValidationLogicBugPocTest.php`
  - `tests/Unit/BugPoc/AdditionalValidationLogicBugPocTest.php`
  - `tests/Unit/BugPoc/CollectionItemDottedRulesSkippedBugPocTest.php`
  - `tests/Unit/BugPoc/NestedStrictErrorKeyBugPocTest.php`
  - `tests/Unit/AsDto/NestedDtoTest.php`
- Out of scope:
  - Key-mapping contract except where hydration prefixes nested cast errors.
  - DTO schema source-of-truth design except as it affects runtime cast decisions.
  - Public API exception taxonomy outside hydration/casting behavior.
  - Source changes; this report is review-only.

## Commands Run

```bash
git status --short
git diff HEAD
vendor/bin/phpunit --filter AutoCast
vendor/bin/phpunit --filter Enum
vendor/bin/phpunit --filter Date
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist

php -r 'require "vendor/autoload.php"; use Axiom\DataForge\Concerns\AsDto; use Axiom\DataForge\Exceptions\ValidationException; final class Agent04FloatDto { use AsDto; public function __construct(public readonly float $amount) {} } try { $dto = Agent04FloatDto::fromArray(["amount" => 1]); echo "OK ".get_debug_type($dto->amount)." ".$dto->amount.PHP_EOL; } catch (Throwable $e) { echo get_class($e).PHP_EOL; if ($e instanceof ValidationException) { var_export($e->errors); echo PHP_EOL; } }'

php -r 'require "vendor/autoload.php"; use Axiom\DataForge\Attributes\DateFormat; use Axiom\DataForge\Concerns\AsDto; use Axiom\DataForge\Exceptions\ValidationException; use Carbon\Carbon; final class Agent04BadTimezoneDto { use AsDto; public function __construct(#[DateFormat("Y-m-d", timezone: "Not/AZone")] public readonly Carbon $date) {} } try { Agent04BadTimezoneDto::fromArray(["date" => "2024-01-02"]); echo "OK".PHP_EOL; } catch (Throwable $e) { echo get_class($e).PHP_EOL; if ($e instanceof ValidationException) { var_export($e->errors); echo PHP_EOL; } echo ($e->getPrevious() ? get_class($e->getPrevious()).PHP_EOL : ""); }'

php -r 'require "vendor/autoload.php"; use Axiom\DataForge\Concerns\AsDto; use Axiom\DataForge\Exceptions\ValidationException; enum Agent04UnitStatus { case Draft; } final class Agent04UnitEnumDto { use AsDto; public function __construct(public readonly Agent04UnitStatus $status) {} } try { $dto = Agent04UnitEnumDto::fromArray(["status" => "Draft"]); echo "OK ".$dto->status->name.PHP_EOL; } catch (Throwable $e) { echo get_class($e).PHP_EOL; if ($e instanceof ValidationException) { var_export($e->errors); echo PHP_EOL; } }'
```

Result:

- PHPStan: passed, `[OK] No errors`.
- PHPUnit:
  - `AutoCast`: passed, 7 tests / 21 assertions.
  - `Enum`: passed, 5 tests / 6 assertions.
  - `Date`: passed, 24 tests / 46 assertions.
- Pint/composer validate if run: not run.
- `git status --short`: no output before writing this review file.
- `git diff HEAD`: no output before writing this review file.
- Repro one-liners:
  - `float $amount` with integer input returned `Axiom\DataForge\Exceptions\ValidationException` on `amount`.
  - invalid `DateFormat` timezone returned `Axiom\DataForge\Exceptions\ValidationException` on `date`.
  - unit enum name input returned `Axiom\DataForge\Exceptions\ValidationException` on `status`.

## Verdict

Short conclusion: needs fix before merge. The common enum/date paths pass, but hydration/casting still has a scalar semantic mismatch and one configuration-error masking issue. The unit enum behavior is either a docs-only contract issue or missing functionality, depending on the intended enum support.

## Findings

### F-01 — Float fields reject integer input

Severity: Medium  
Type: Semantic contract issue  
Confidence: High  
Root cause: builtin scalar fields skip hydration casting entirely, and `TypeSpec` checks reflected builtin names with exact normalized `gettype()` equality.  
Related symptoms:

- `float` DTO fields reject integer values even though PHP accepts integers for float-typed parameters.
- Numeric validation helpers treat `int` and `float` together, but final type validation does not.

Evidence:

- `src/Core/DtoHydrator.php:82`
- `src/Core/DtoHydrator.php:83`
- `src/Core/TypeSpec.php:140`
- `src/Core/TypeSpec.php:143`

Actual behavior:

```php
final class PriceDto
{
    use AsDto;

    public function __construct(public readonly float $amount) {}
}

PriceDto::fromArray(['amount' => 1]);
```

Actual result:

```text
Axiom\DataForge\Exceptions\ValidationException
amount => The amount field has an invalid type.
```

Expected behavior:

Integer numeric input should be accepted for a `float` DTO field and hydrated as `1.0`, matching PHP's normal typed-parameter behavior for `float`.

Impact:

- Real numeric payloads such as JSON integers fail when DTOs declare decimal fields as `float`.
- Users may add unnecessary custom rules or pre-normalization outside Data Forge.
- The package's "strict scalar, not broad string auto-cast" contract remains intact if only `int -> float` is allowed.

Why this happened:

- classify cause: semantic mismatch between PHP type semantics and exact runtime type-name comparison.

Recommendation:

- Update `TypeSpec::namedTypeAccepts()` so reflected `float` accepts both `float` and `int`.
- Keep string-to-float casting unsupported unless the public scalar casting contract is intentionally expanded.
- Add focused tests for `float` with `1`, `1.5`, and `'1.5'` to lock the boundary.

### F-02 — Invalid DateFormat timezone is reported as invalid input

Severity: Medium  
Type: Bug  
Confidence: High  
Root cause: date casting catches all `Throwable`, so configuration errors from creating `DateTimeZone` are converted into ordinary field validation failures.  
Related symptoms:

- Invalid attribute timezone is hidden as a user-input `ValidationException`.
- The real configuration problem is not visible unless the developer debugs inside `DtoHydrator`.

Evidence:

- `src/Core/DtoHydrator.php:373`
- `src/Core/DtoHydrator.php:379`
- `src/Core/DtoHydrator.php:416`
- `src/Attributes/DateFormat.php:12`

Actual behavior:

```php
final class BadDateDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d', timezone: 'Not/AZone')]
        public readonly Carbon $date,
    ) {}
}

BadDateDto::fromArray(['date' => '2024-01-02']);
```

Actual result:

```text
Axiom\DataForge\Exceptions\ValidationException
date => The date field has an invalid type.
```

Expected behavior:

Invalid `DateFormat` metadata should surface as an inspection/configuration failure, not as invalid user input. A reasonable target is `InspectionException` with the original timezone exception chained.

Impact:

- DTO authors get misleading user-input errors for a broken attribute.
- Operational debugging is harder because the real exception is swallowed.
- This weakens the package distinction between validation failures and inspection/configuration failures.

Why this happened:

- classify cause: design boundary leak; one catch block handles both user-input parse failures and developer configuration failures.

Recommendation:

- Validate `DateFormat` timezone when inspecting/building the attribute, or split cast errors into configuration failures versus parse failures.
- Do not catch `DateTimeZone` construction failures as invalid input.
- Add a regression test with an invalid timezone expecting an inspection/configuration exception.

### F-03 — Unit enum name input is not supported or documented as unsupported

Severity: Low  
Type: Docs drift  
Confidence: Medium  
Root cause: the validation projector knows how to project `UnitEnum` instances by name, but the hydrator only casts backed enums from backing values.  
Related symptoms:

- Existing enum tests cover backed enums, but not unit enum string names.
- The README says "backed enums", while the review scope and projector behavior can make unit enum handling look partially supported.

Evidence:

- `src/Core/DtoHydrator.php:352`
- `src/Core/DtoHydrator.php:356`
- `src/Core/ValidationProjector.php:181`
- `src/Core/ValidationProjector.php:182`
- `README.md:232`

Actual behavior:

```php
enum Status
{
    case Draft;
}

final class UnitEnumDto
{
    use AsDto;

    public function __construct(public readonly Status $status) {}
}

UnitEnumDto::fromArray(['status' => 'Draft']);
```

Actual result:

```text
Axiom\DataForge\Exceptions\ValidationException
status => The status field has an invalid type.
```

Expected behavior:

If unit enum name input is part of the intended enum contract, hydrate `Status::Draft` from `'Draft'`. If not, the package should explicitly document that only backed enum scalar input is supported and add a test proving unit enum names are rejected.

Impact:

- Users may assume all PHP enums are supported from strings.
- Internal projection support for `UnitEnum` can be mistaken for boundary casting support.
- This is lower risk because README currently names backed enums specifically.

Why this happened:

- classify cause: semantic boundary ambiguity between validation projection of typed instances and raw input hydration.

Recommendation:

- Decide the unit enum contract explicitly.
- If supported, implement name-to-case casting with duplicate-safe enum reflection and invalid-name preservation for validation.
- If unsupported, document this in Auto-Casting and add coverage for rejection.

## False Positives Checked

- FP-01: Invalid backed enum strings do not leak native `ValueError` or `TypeError`; invalid string-backed and int-backed enum input returns `ValidationException`.
- FP-02: `DateFormat` is synchronized for the main happy path: both attribute-based and custom `date_format` rules drive pre-hydration validation and date casting.
- FP-03: Empty string for nullable date-like fields is handled intentionally; blank string becomes `null` for nullable date-like targets instead of parsing to "now".
- FP-04: Nested custom `date_format` rules are forwarded into child DTO hydration via `RuleScope::nestedRules()`.
- FP-05: Collection item validation errors preserve indexed paths such as `people.0.age` and strict nested unknown keys preserve raw leaf keys.
- FP-06: Builtin scalar strings are intentionally not broadly cast from strings, matching `AGENTS.md`; the float finding is only about integer-to-float numeric compatibility.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
| --- | --- | --- | --- |
| `float` accepts integer numeric input | Numeric min/max tests cover numeric semantics, not final `float` type acceptance | `float $amount` with `1` is rejected | Add `FloatInputPocDto::fromArray(['amount' => 1])` expecting `1.0` |
| String numeric input remains rejected for builtin scalars | Contract exists in `AGENTS.md`; coverage is indirect | No explicit boundary test for `'1.5'` into `float` | Add paired rejection test for `FloatInputPocDto::fromArray(['amount' => '1.5'])` |
| Invalid `DateFormat` timezone | Date format happy path and invalid raw date are covered | Bad attribute configuration is masked as validation | Add invalid timezone test expecting `InspectionException` or the chosen configuration exception |
| Unit enum boundary behavior | Backed enum casting is covered | Unit enum string names are not covered either as accepted or rejected | Add explicit test for `UnitEnumDto::fromArray(['status' => 'Draft'])` according to chosen contract |
| Formatted date parser warning strictness | Invalid format string coverage exists for common mismatch | No focused test for partial/overflow dates such as `31/02/2024` | Add `DateFormat('d/m/Y')` overflow test documenting Laravel/Carbon strictness expectation |

## Open Questions

- Should `int -> float` be treated as acceptable PHP scalar compatibility while still rejecting all string-to-scalar casts?
- Is unit enum name input intended to be supported, or should public docs say only backed enum values are cast?
- Should invalid `DateFormat` timezone be rejected at schema compile time, rule-build time, or hydration time?
- Should date casting use stricter `createFromFormat` warning checks so parser warnings cannot silently produce adjusted dates?

## Final Assessment

`DtoHydrator` is functionally stable for the currently covered backed-enum and date-like happy paths, and the requested PHPUnit/PHPStan checks pass. However, it is accumulating too many responsibilities: nested DTO validation, collection hydration, error path prefixing, enum casting, date-format extraction, and date parsing all live in one class. That shape is not yet a hard failure, but it is trending toward a god-class. The merge should fix the `float` semantic mismatch and the swallowed `DateFormat` configuration error, then either implement or explicitly reject unit enum string-name hydration.
