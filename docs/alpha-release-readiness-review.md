# Alpha Release Readiness Review

Date: 2026-06-02

## Verdict

Data Forge is focused on a narrow public alpha around the core DTO boundary
pipeline.

The release focus is:

```text
raw input -> strict preflight -> canonical key normalization -> validation/project -> hydration/cast -> typed validation -> DTO instantiation
```

## Alpha Scope

The alpha package centers on:

- `AsDto::fromArray()`
- `AsDto::fromJson()`
- `AsDto::fromArrayable()`
- `AsStrictInputDto`
- `#[MapKey]`
- validation rules/messages
- nested DTO and collection hydration
- backed enum and date-like casting

## Remaining Public Alpha Blockers

- Add direct dependency metadata for directly imported runtime libraries.
  Carbon support is documented and Carbon classes are imported directly by
  source files, but `nesbot/carbon` currently arrives transitively through
  Illuminate.

- Publish and document an alpha install path.
  README says `composer require axiom/data-forge`, but alpha users need a clear
  version or stability command such as a future `0.1.0-alpha1` tag, or an
  explicit dev-main/VCS install path if the package is not on Packagist yet.

- State Laravel compatibility precisely.
  `composer.json` requires `illuminate/*` `^10.48`. Laravel 11/12 projects will
  not be quick installs until constraints and CI coverage are expanded, or docs
  clearly say alpha targets Laravel 10 only.

- Keep the quality gate green.
  The required release gate is PHPStan, PHPUnit, and Pint.

## Current Gate Results

- `composer validate --strict`: pass.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist`: pass, no errors.
- `vendor/bin/phpunit`: pass, 280 tests and 667 assertions.
- `vendor/bin/pint --test`: pass.

## Alpha Decision

Internal alpha is reasonable after the cleanup.

Public alpha should wait until the remaining dependency metadata,
install/version docs, Laravel compatibility statement, and quality checks are
green.
