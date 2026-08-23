# Alpha Testing Review

Date: 2026-06-02

## Verdict

The test suite focuses on the core DTO boundary: input mapping, strictness,
validation, hydration, casting, defaults, and nested collections.

## Current Suite

- PHPUnit: 280 tests and 667 assertions, passing locally on PHP 8.5.6.
- PHPStan: level max, passing for `src/`.
- Pint: passing with `vendor/bin/pint --test`.
- Composer validation: pass.

## Strengths

- The suite covers raw-to-canonical key mapping, `#[MapKey]`, strict unknown-key
  behavior, and external error paths.
- Feature tests cover public `AsDto` behavior across validation, nullable and
  default values, nested DTOs, collections, and root helpers.
- Hydration tests cover backed enums, date-like values, Carbon, DateTime, and
  negative guards against broad scalar coercion.
- Spatie-inspired tests are being used as edge-case sources without making
  Spatie compatibility the product contract.
- Many previously logged bug classes now have focused regression coverage.

## Remaining Gaps

- Add direct `ValidationProjector` tests for deeper nested mapped collections.
- Add strict unknown-key tests under mapped parent plus mapped collection plus
  strict mapped child.
- Add mixed collection aggregation tests where one payload contains multiple
  invalid item shapes, missing fields, and mapped-field failures.
- Add explicit unsupported/rejected unit enum behavior.
- Enable coverage reporting in CI or document a local coverage workflow.

## Alpha Decision

Testing is good enough for a caveated alpha after the remaining release blockers
are fixed and the post-cleanup PHPStan/PHPUnit/Pint gate is green.
