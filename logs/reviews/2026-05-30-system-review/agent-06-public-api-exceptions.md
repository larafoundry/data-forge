# Agent 06 — Public API & Exception Contract

## Scope

- Files reviewed:
  - `src/Concerns/AsDto.php`
  - `src/Exceptions/*`
  - `src/Core/ContainerHelper.php`
  - public validation helpers around `src/Core/Validator.php`, `src/Core/InputMapper.php`, and `src/Core/DtoContract.php`
- Tests reviewed:
  - `tests/Unit/AsDto/*`
  - `tests/Unit/ContainerHelper/ContainerHelperTest.php`
  - relevant bug POCs for exception contracts and defaults
- Out of scope:
  - Source-code changes
  - Aggregating other agents' reports
  - Non-public internals except where they directly affect public API behavior

## Commands Run

```bash
git status --short
git diff HEAD
vendor/bin/phpunit --filter AsDto
vendor/bin/phpunit --filter ContainerHelper
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
```

Additional read-only repro probes were run with `php -r` to confirm exception types and error shapes for duplicate `MapKey`, invalid hook return values, default validation messages, and non-public constructor behavior.

Result:

- PHPStan: passed, no errors.
- PHPUnit: passed.
  - `AsDto`: 64 tests, 196 assertions.
  - `ContainerHelper`: 14 tests, 20 assertions.
- Pint/composer validate if run: not run. This was a review-only task and no source code was changed.

## Verdict

Needs fix before merge for public API and exception-contract stability. Core tests and PHPStan are green, but several misconfiguration paths are exposed as validation/user-input failures or silently ignored.

## Findings

### F-01 — Duplicate MapKey Is Reported As User Validation

Severity: Medium
Type: Semantic contract issue
Confidence: High
Root cause: A DTO configuration error is detected in the input mapping step and represented as a `ValidationException`.
Related symptoms:

- Duplicate external keys produce an artificial `key_mapping` error bag.
- The test suite currently locks in `ValidationException` for this inspection/configuration problem.

Evidence:

- `src/Core/InputMapper.php:82`
- `src/Core/InputMapper.php:99`
- `tests/Unit/BugPoc/DataContractBugPocTest.php:71`

Actual behavior:

```php
final class DuplicateMappedKeyDto
{
    use AsDto;

    #[MapKey('shared')]
    public ?string $first = null;

    #[MapKey('shared')]
    public string $second;
}

DuplicateMappedKeyDto::fromArray(['shared' => 'value']);
// throws ValidationException with errors['key_mapping']
```

Expected behavior:

Duplicate `MapKey` definitions are a DTO inspection/configuration failure and should surface as `InspectionException`, not as invalid caller input.

Impact:

- Consumers cannot reliably distinguish bad payloads from broken DTO definitions.
- The public `ValidationException::$errors` shape contains a synthetic key that is not an input key or canonical DTO key.
- This weakens the documented boundary: invalid user input is validation, inspection/configuration problems are inspection.

Why this happened:

- Cause classification: semantic mismatch at the design boundary between schema inspection and input validation.

Recommendation:

- Move duplicate input-key detection into schema compilation or make `InputMapper::assertNoDuplicateMappedKeys()` throw `InspectionException`.
- Update tests to assert `InspectionException` for duplicate mapping.

### F-02 — Non-Array rules/messages Hook Results Are Silently Ignored

Severity: Medium
Type: Semantic contract issue
Confidence: High
Root cause: `DtoContract` treats non-array `rules()` and `messages()` return values as absent hooks.
Related symptoms:

- A bad `rules()` implementation can disable validation instead of failing fast.
- A bad `messages()` implementation can silently drop custom messages.

Evidence:

- `src/Core/DtoContract.php:19`
- `src/Core/DtoContract.php:63`
- `src/Concerns/AsDto.php:66`
- `src/Concerns/AsDto.php:82`

Actual behavior:

```php
final class BadRulesDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    public static function rules()
    {
        return 'name|required';
    }
}

BadRulesDto::fromArray(['name' => 'Ada']);
// succeeds; the invalid rules result is treated as []
```

Expected behavior:

Invalid static hook return values should throw `InspectionException`, consistent with invalid rule field names, invalid rule item types, and invalid message item types.

Impact:

- DTO authors can ship broken validation with no signal.
- API behavior becomes unpredictable because a typo in hook return shape changes runtime validation behavior.

Why this happened:

- Cause classification: semantic mismatch and insufficient coverage for malformed public hooks.

Recommendation:

- Change non-array hook results to `InspectionException`.
- Add focused tests for non-array `rules()` and non-array `messages()`.

### F-03 — Default Validation Messages Leak Laravel Translation Keys

Severity: Medium
Type: Design smell
Confidence: High
Root cause: The validator is created with an empty `ArrayLoader`, so untranslated Laravel validation keys become the public error messages.
Related symptoms:

- `ValidationException::$errors` contains strings like `validation.required`.
- `ValidationException::getMessage()` flattens those untranslated keys into a hard-to-use exception message.

Evidence:

- `src/Core/Validator.php:41`
- `src/Core/Validator.php:276`
- `src/Core/Validator.php:278`
- `src/Exceptions/ValidationException.php:12`
- `src/Exceptions/ValidationException.php:23`

Actual behavior:

```php
DtoWithRules::fromArray([
    'name' => 'Ada',
    'email' => 'ada@example.com',
]);

// ValidationException::$errors includes:
// ['age' => ['validation.required']]
```

Expected behavior:

Either package-owned human-readable default messages or a documented stable machine-readable error shape. Public API should not expose framework translation internals by default.

Impact:

- API consumers receive low-value error messages unless every DTO supplies custom messages.
- Error messages are tied to Laravel internals rather than a package-owned contract.

Why this happened:

- Cause classification: implementation detail leaking through the public exception surface.

Recommendation:

- Load Laravel's default validation translations, provide package default messages, or document that `$errors` is machine-readable and intentionally translation-key based.
- Consider separating a stable error code from a display message.

### F-04 — AsDto Throws Annotation Leaks Throwable

Severity: Low
Type: Docs drift
Confidence: High
Root cause: `AsDto` PHPDoc exposes broad implementation-level exceptions instead of the package exception taxonomy.
Related symptoms:

- `fromArray()` and `fromArrayStrict()` advertise `ReflectionException|Throwable`.
- The implementation primarily routes failures through `ValidationException`, `InspectionException`, and `InstantiationException`.

Evidence:

- `src/Concerns/AsDto.php:23`
- `src/Concerns/AsDto.php:38`
- `src/Concerns/AsDto.php:92`
- `src/Concerns/AsDto.php:97`
- `src/Concerns/AsDto.php:105`

Actual behavior:

```php
/**
 * @throws ValidationException|ReflectionException|Throwable
 */
public static function fromArray(array $attributes): static
```

Expected behavior:

The public API documentation should describe stable package exceptions, not broad implementation details.

Impact:

- Consumers cannot tell which exception types are contractual.
- Static analysis and generated docs overstate the catch surface.

Why this happened:

- Cause classification: docs drift from the current exception hierarchy.

Recommendation:

- Update throws annotations to the package-level contract, for example `ValidationException|InspectionException|InstantiationException|DataForgeException` as appropriate.
- Avoid advertising `Throwable` unless truly intended as part of the public contract.

## False Positives Checked

- FP-01: Non-public constructor behavior was rechecked with actual private/protected constructors. `ContainerHelper` wraps reflection failures in `InstantiationException`, which matches the requested exception taxonomy.
- FP-02: Readonly constructor-promoted DTOs work because constructor parameters are populated before readonly checks. Public readonly properties without constructors fail with `InstantiationException`, which is acceptable.
- FP-03: Top-level vs nested `fromArray()` behavior is documented and tested: nested DTO hydration does not call custom child `fromArray()` overrides.
- FP-04: No `toArray`, `jsonSerialize`, or public serialization helper was found in `src`, so there is no serialization API to review.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
| --- | --- | --- | --- |
| Duplicate `MapKey` exception type | Covered as `ValidationException` | Contract should likely be `InspectionException` | Assert duplicate mapped keys throw `InspectionException` during `fromArray()` |
| Non-array `rules()` return | Partial invalid rule-shape coverage exists | Non-array hook result is silently ignored | DTO with untyped `rules()` returning string should throw `InspectionException` |
| Non-array `messages()` return | Invalid message item type is covered | Non-array hook result is silently ignored | DTO with untyped `messages()` returning string should throw `InspectionException` |
| Default validation message shape | Tests mostly assert exception class | Untranslated `validation.*` keys are not asserted or rejected | Assert default `ValidationException::$errors` contains usable package messages or documented codes |
| Actual private/protected constructors | Current fixtures have public constructors despite names | Visibility behavior is not directly covered | Add real private/protected constructor fixtures and assert `InstantiationException` |

## Open Questions

- Should DTO configuration errors discovered during mapping be reported only by `InspectionException`, even when discovered late in the validation pipeline?
- Is `ValidationException::$errors` intended to contain display-ready messages, Laravel translation keys, or stable machine-readable codes?
- Should `fromArray()` PHPDoc document only package-specific exceptions, or intentionally expose arbitrary userland exceptions from DTO hooks?

## Final Assessment

The public API shape is mostly coherent: `fromArray()`, strict input, canonical keys, default constructor values, nested DTO behavior, and readonly constructor-promoted DTOs behave predictably. The remaining risk is concentrated around the exception contract. A few configuration failures are either reported as user validation or silently ignored, and default validation messages currently leak Laravel translation internals. I would fix those before merging a public contract release, because they affect how consumers catch, classify, and display failures.
