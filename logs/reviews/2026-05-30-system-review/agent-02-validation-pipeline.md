# Agent 02 — Validation Pipeline Design

## Scope

- Files reviewed:
  - `src/Core/Validator.php`
  - `src/Core/ValidationProjector.php`
  - `src/Core/StrictInputValidator.php`
  - `src/Core/InputMapper.php`
  - `src/Exceptions/ValidationException.php`
- Tests reviewed:
  - tests matched by `Validator`
  - tests matched by `Nested`
  - full PHPUnit suite
- Out of scope:
  - Implementing fixes
  - Changing validation rules or public API behavior
  - Reviewing DTO schema internals except where they affect validation pipeline order
  - Aggregating other agents' findings

## Commands Run

```bash
git status --short
git diff HEAD
vendor/bin/phpunit --filter Validator
vendor/bin/phpunit --filter Nested
vendor/bin/phpunit
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
```

Result:

- PHPStan: passed, no errors.
- PHPUnit: passed. `Validator` passed with 12 tests / 35 assertions; `Nested` passed with 31 tests / 58 assertions; full suite passed with 189 tests / 450 assertions.
- Pint/composer validate if run: not run.

## Verdict

Short conclusion: **needs fix before merge** for one strict-preflight exception leak; otherwise the pipeline order is mostly clean with light design debt in `Validator` orchestration.

## Findings

### F-01 — Numeric Root Keys Leak `TypeError` During Strict Preflight

Severity: High  
Type: Bug  
Confidence: High  
Root cause: `StrictInputValidator::unknownKeyErrors()` passes an array key directly into `joinPath()` even though PHP arrays can contain integer keys.  
Related symptoms:

- Numeric-string keys can become integer array keys before validation.
- Invalid user input can escape the package exception contract as a raw PHP `TypeError`.

Evidence:

- `src/Core/StrictInputValidator.php:100`
- `src/Core/StrictInputValidator.php:105`

Actual behavior:

```php
ReviewStrictNumericKeyDto::fromArrayStrict([
    'name' => 'Ada',
    '0' => 'bad',
]);

// Actual:
// TypeError: StrictInputValidator::joinPath(): Argument #2 ($field)
// must be of type string, int given
```

Expected behavior:

Invalid caller input should be rejected through the package's stable user-input exception path, preferably `UnknownInputKeyException` for strict unknown keys, without leaking a raw `TypeError`.

Impact:

- Strict validation can fail outside the documented exception contract.
- Callers catching `ValidationException` / `UnknownInputKeyException` may miss this failure.
- Numeric JSON/object-like input keys can trigger framework-level behavior rather than package-level behavior.

Why this happened:

- Classify cause: semantic mismatch and insufficient coverage.
- The validator assumes key segments are strings, while PHP arrays preserve integer keys and cast numeric string keys to integers.

Recommendation:

- Normalize key path segments to string before calling `joinPath()`, or explicitly reject non-string keys as `UnknownInputKeyException`.
- Add regression tests for strict root numeric key and numeric-string key inputs.

### F-02 — `Validator` Is Becoming a Broad Orchestration Boundary

Severity: Low  
Type: Design smell  
Confidence: Medium  
Root cause: `Validator` owns orchestration, Laravel validator wrapping, stateful error collection, root/nested exception boundaries, key mapping, and pre/post validation staging.  
Related symptoms:

- Pipeline responsibilities are readable today but easy to blur when new phases are added.
- Future mapped/nested validation changes could be placed on the wrong side of the raw/canonical boundary.

Evidence:

- `src/Core/Validator.php:142`
- `src/Core/Validator.php:167`
- `src/Core/Validator.php:233`
- `src/Core/Validator.php:264`

Actual behavior:

`Validator` coordinates strict preflight, input normalization, projected validation, hydration, typed validation, error mapping, and exception wrapping in one class.

Expected behavior:

The current behavior is not wrong, but each pipeline phase should have a crisp owner so future changes do not mix raw input keys, canonical keys, and typed data.

Impact:

- This is design debt, not a current behavior bug.
- Risk grows if new validation phases, nested rules, or alternative error mapping behavior are added.

Why this happened:

- Classify cause: design boundary.
- `Validator` is the natural public orchestrator, but it is accumulating phase-specific responsibilities.

Recommendation:

- Keep the current structure for small fixes.
- If the pipeline expands, split a stateless pipeline runner/result object from the public `Validator` boundary, leaving `Validator` focused on API-facing exception behavior.

## False Positives Checked

- FP-01: Strict preflight runs before normalization and hydration.
- FP-02: Unknown string keys were not observed to be masked by `required`, `array`, or nested required validation.
- FP-03: `ValidationProjector` did not appear to drop accepted nested data too early in the reviewed cases.
- FP-04: Pre-validation and post-validation have a clear practical split through raw/projected rules before hydration and typed validation after hydration.
- FP-05: Laravel validator failures are wrapped into the package `ValidationException` through the reviewed path.
- FP-06: No additional `ValueError` or `ReflectionException` leak was found in this scope.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
| --- | --- | --- | --- |
| Strict root numeric key | Strict string-key behavior is covered | Numeric and numeric-string PHP array keys are not covered | Assert `fromArrayStrict([0 => 'bad'])` produces `UnknownInputKeyException` |
| Strict root numeric-string key | Strict string-key behavior is covered | PHP casts `'0'` to `0`, exposing the same issue | Assert `fromArrayStrict(['0' => 'bad'])` follows package exception contract |
| Invalid array-key shapes at root boundary | General validation suite passes | Edge keys are not locked | Add a focused strict preflight data provider for integer keys |
| Pipeline phase ownership | Behavior tests cover current flow | No architectural guard | Add narrow regression tests around raw/canonical error boundaries when adding new phases |

## Open Questions

- Should non-string array keys be reported exactly as stringified path segments, or rejected with a dedicated message?
- Should `UnknownInputKeyException` remain separate from `ValidationException` for all strict preflight failures, including non-string key cases?

## Final Assessment

The validation pipeline is mostly in the expected order: raw strict preflight, canonical normalization, projected validation, hydration/cast, typed validation, then instantiation. The main behavior issue is the strict numeric-key `TypeError` leak, which violates the public exception contract for invalid user input. Beyond that, the design is serviceable but `Validator` should be watched because it is the place where future changes are most likely to blur raw, canonical, and typed boundaries.
