# Agent 07 — Dependencies, Tooling, Docs

## Scope

- Files reviewed:
  - `composer.json`
  - `composer.lock`
  - `.github/workflows/tests.yml`
  - `phpstan.neon.dist`
  - `phpunit.xml`
  - `pint.json`
  - `README.md`
  - `docs.md`
  - `docs/DATA.md`
  - `AGENTS.md`
  - `logs/bugs/*.md`
  - `logs/improvements/*.md`
- Tests reviewed:
  - Test suite result from `vendor/bin/phpunit`
  - Dependency/docs evidence from `tests/Unit/**` references where needed
- Out of scope:
  - Source code changes
  - Fixing composer metadata, workflows, docs, or logs
  - Aggregating other agents' findings
  - Deep behavioral review of validation, hydration, or schema internals except where needed to verify documentation/tooling claims

## Commands Run

```bash
git status --short
git diff HEAD
composer validate
composer validate --strict
composer show
composer show --direct
composer install --dry-run --no-interaction --prefer-dist --no-progress
composer depends nesbot/carbon
composer depends psy/psysh
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
vendor/bin/phpunit
vendor/bin/pint --test
composer phpstan
composer test
composer pint -- --test
rg --files -g 'composer.json' -g 'composer.lock' -g '.github/workflows/*' -g 'phpstan.neon.dist' -g 'phpunit.xml' -g 'pint.json' -g 'README.md' -g 'docs.md' -g 'docs/**' -g 'AGENTS.md' -g 'logs/**'
rg -n 'static::rules|DtoContract|canonical|internal property|external input|ErrorKeyMapper|ContainerHelper|Auto-Casting|Rule objects|ValidationRule|closures|string' README.md docs.md docs/DATA.md AGENTS.md
rg -n 'psysh|Carbon\\|Illuminate\\' composer.json README.md docs.md docs/DATA.md src tests
```

Result:

- PHPStan: PASS, no errors.
- PHPUnit: PASS, 189 tests and 450 assertions.
- Pint/composer validate if run: PASS for `vendor/bin/pint --test`, `composer pint -- --test`, `composer validate`, and `composer validate --strict`.
- Composer lock consistency: PASS, `composer install --dry-run` reported nothing to install, update, or remove.
- Working tree before report: clean for source review, `git status --short` and `git diff HEAD` had no output.

## Verdict

Needs fix before merge for dependency metadata and documentation. `docs.md` describes older key-mapping and validation internals.

## Findings

### F-01 — Carbon is used directly but only present transitively

Severity: Medium  
Type: Dependency metadata issue  
Confidence: High  
Root cause: Source files import Carbon classes directly, but `nesbot/carbon` is not declared as a direct dependency.
Related symptoms:

- README advertises Carbon and CarbonImmutable casting support.
- `composer depends nesbot/carbon` shows Carbon arrives through `illuminate/support`, not this package's own metadata.

Evidence:

- composer.json:4
- composer.json:10
- README.md:15
- README.md:235
- src/Core/DtoHydrator.php:14
- src/Core/DtoHydrator.php:15

Actual behavior:

```bash
composer depends nesbot/carbon
```

Output shows `illuminate/support v10.49.0 requires nesbot/carbon (^2.67)`.

Expected behavior:

If the package directly imports and documents Carbon support, `nesbot/carbon` should be a direct `require` dependency.

Impact:

- The package relies on an implementation detail of another dependency.
- Future Illuminate dependency changes could break Data Forge's advertised Carbon feature unexpectedly.
- Metadata readers do not see Carbon as a deliberate package contract.

Why this happened:

- Cause: design boundary between direct package features and transitive framework dependencies was not reflected in Composer metadata.

Recommendation:

- Add `nesbot/carbon` to `require` with a constraint compatible with Illuminate 10 and PHP 8.1.

### F-02 — `docs.md` describes obsolete validation and key-mapping internals

Severity: Medium  
Type: Docs drift  
Confidence: High  
Root cause: `docs.md` was not rewritten after the schema/compiler and canonical validation pipeline refactor.
Related symptoms:

- It says `AsDto` calls `static::rules()` and `static::messages()` directly.
- It says validation errors are mapped back to internal property names.
- It says enum casting calls `from()`.
- It says `ContainerHelper` still remaps external `MapKey` input keys.
- It does not clearly state string-rules-only.
- It begins with conversational text that does not belong in repo docs.

Evidence:

- docs.md:1
- docs.md:15
- docs.md:59
- docs.md:61
- docs.md:62
- docs.md:124
- docs.md:126
- docs.md:156
- src/Concerns/AsDto.php:97
- src/Core/DtoSchemaCompiler.php:70
- src/Core/DtoContract.php:17
- src/Core/Validator.php:164
- src/Core/ErrorKeyMapper.php:10
- src/Core/ContainerHelper.php:26

Actual behavior:

`docs.md` says ordinary validation failures are converted to internal property-name keys, while the current root validator catches canonical validation errors and maps them to caller input keys through `ErrorKeyMapper`.

Expected behavior:

Docs should describe the current flow:

```text
raw input -> strict preflight -> normalize to canonical keys -> validate/project -> hydrate/cast -> validate typed data -> instantiate DTO
```

Root validation errors should be documented as external input-keyed. Successful validated data and DTO construction should be canonical-keyed.

Impact:

- Users and agents may write incorrect integrations or tests.
- Future changes may accidentally revive old behavior because the long-form docs endorse it.
- The docs contradict `AGENTS.md`, which currently has the correct mental model.

Why this happened:

- Cause: documentation drift after a design boundary change.

Recommendation:

- Rewrite or retire `docs.md`.
- Align it with `AGENTS.md`, `README.md`, and current code.
- Add an explicit note that static validation rules are string-only: pipe strings or arrays of strings; no rule objects, `ValidationRule` instances, or closures.

### F-03 — README auto-casting wording compresses the current multi-stage flow

Severity: Low  
Type: Docs drift  
Confidence: High  
Root cause: README still says compatible strings are cast before validation, but current validation has raw-shape and projected validation before hydration/casting.
Related symptoms:

- README is mostly aligned, but this wording can mislead readers about when date and enum values are converted.

Evidence:

- README.md:227
- README.md:229
- src/Core/Validator.php:242
- src/Core/Validator.php:247
- src/Core/Validator.php:256
- src/Core/Validator.php:257

Actual behavior:

The validator runs raw-shape rules, projects values for pre-hydration validation, hydrates/casts through `DtoHydrator`, then validates typed hydrated data.

Expected behavior:

README should describe casting as part of the validation/hydration pipeline rather than simply "before validation".

Impact:

- Low user-facing risk, but this matters for date-format rules, raw array rules, and nested DTO behavior.

Why this happened:

- Cause: semantic mismatch between a simplified README sentence and the actual staged pipeline.

Recommendation:

- Change the Auto-Casting section wording to "during the validation and hydration pipeline".
- Consider adding one sentence that raw shape rules can run before casting, while final DTO construction uses canonical hydrated values.

### F-04 — `phpunit.xml` includes a missing `app` directory

Severity: Low  
Type: Tooling drift  
Confidence: High  
Root cause: The PHPUnit source include block appears inherited from an application layout, but this repository is a package with `src/` and no `app/`.
Related symptoms:

- No test failure, but the package configuration is noisier than necessary.

Evidence:

- phpunit.xml:12
- phpunit.xml:14

Actual behavior:

```bash
test -d app && printf 'app exists\n' || printf 'app missing\n'
```

Output: `app missing`.

Expected behavior:

Package PHPUnit config should include only existing package source directories unless there is a deliberate generated path.

Impact:

- Harmless locally today, but confusing in a package and could affect future coverage/source configuration.

Why this happened:

- Cause: careless local config carryover from app-style defaults.

Recommendation:

- Remove `<directory>app</directory>` from `phpunit.xml`.

### F-05 — Several bug logs reference removed caster/transformer concepts

Severity: Low  
Type: Docs drift  
Confidence: High  
Root cause: Historical bug notes were kept after implementation moved to the current `DtoHydrator` casting path.
Related symptoms:

- Logs mention `CarbonCaster`, `EnumCaster`, `StringCaster`, and `ITransformer`, none of which appear in current source.
- One nested MapKey log says "current code" fails even though the same file later documents the fix.

Evidence:

- logs/bugs/carbon-caster-invalid-string-exception.md:9
- logs/bugs/carbon-caster-invalid-string-exception.md:17
- logs/bugs/enum-caster-int-backed-string-type-error.md:9
- logs/bugs/enum-caster-int-backed-string-type-error.md:17
- logs/bugs/string-caster-null-empty-string.md:9
- logs/bugs/nested-mapkey-dotted-rule-uses-internal-child-key.md:27
- src/Core/DtoHydrator.php:346

Actual behavior:

The logs read like current component documentation in places, but they describe old component names and old transformer expectations.

Expected behavior:

Bug logs should either be clearly historical or use current component names and current package contracts.

Impact:

- Agents may search logs and reason about nonexistent classes.
- Future maintenance can drift toward fixing old abstractions instead of current pipeline behavior.

Why this happened:

- Cause: stale historical notes after refactors.

Recommendation:

- Mark these logs as historical/pre-refactor or update terminology to `DtoHydrator::castValue()` and current exception behavior.
- In `nested-mapkey-dotted-rule-uses-internal-child-key.md`, replace "current code" with "before fix".

### F-06 — `psy/psysh` is a questionable dev dependency

Severity: Low  
Type: Dependency hygiene  
Confidence: Medium  
Root cause: `psy/psysh` is declared in `require-dev`, but this review found no composer script or repository reference that uses it.
Related symptoms:

- Dev install includes an interactive shell package without documented workflow.

Evidence:

- composer.json:44
- composer show --direct output included `psy/psysh 0.12.22`

Actual behavior:

`psy/psysh` is installed for development, but no reviewed docs or scripts point contributors to it.

Expected behavior:

Dev dependencies should support a documented local workflow, test/tool command, or contributor task.

Impact:

- Small install and audit surface increase.
- Future maintainers may not know whether it is safe to remove.

Why this happened:

- Cause: dependency hygiene drift.

Recommendation:

- Remove `psy/psysh` if unused.
- If intentionally used for manual debugging, document the workflow or add an explicit composer script.

## False Positives Checked

- FP-01: Composer package type is acceptable. `composer.json` has no `type`, so Composer defaults to `library`, which is suitable for this package. Adding `"type": "library"` would be explicit but not required.
- FP-02: Lock file is consistent. `composer validate --strict` passed and `composer install --dry-run --no-interaction --prefer-dist --no-progress` reported nothing to install, update, or remove.
- FP-03: Dev-tool bump was not detected locally. Locked versions are `laravel/pint v1.20.0`, `phpstan/phpstan 2.1.54`, and `phpunit/phpunit 10.5.63`, matching current constraints.
- FP-04: CI PHP matrix matches the Composer PHP constraint. The workflow tests PHP 8.1 through 8.5, while `composer.json` requires `php: ^8.1`.
- FP-05: `AGENTS.md` is not overloaded for the current system. At 111 lines, it is short enough to read and accurately captures the current raw/canonical key model, strict input behavior, `DtoContract`, string-only rules, and required checks.
- FP-06: `README.md` does state string-rules-only and rejects rule objects, `ValidationRule` instances, and closures. The gap is mainly in `docs.md`.
- FP-07: Required checks passed from both direct vendor commands and composer scripts.

## Missing Coverage

| Behavior | Existing coverage | Gap | Suggested test |
| --- | --- | --- | --- |
| Docs examples stay aligned with current key-mapping/error behavior | README has basic examples and tests cover behavior in code | No docs/example validation check | Add a lightweight docs smoke test for README snippets or maintain executable examples under `tests/Docs` |
| Composer metadata reflects direct source imports | Manual review only | No automated guard for direct class imports from transitive dependencies | Add a dependency analysis tool or CI script that flags source imports from packages not listed in `require` |
| Logs remain historical and do not describe removed classes as current | No coverage | Stale log names can mislead future agents | Add a review checklist item or periodic docs audit for `logs/**` references to removed classes |

## Open Questions

- Should Carbon support be considered an explicit package feature with a direct dependency, or only an incidental capability inherited from Illuminate?
- Is `docs.md` meant to be public documentation, internal agent documentation, or a historical analysis artifact? Its current style suggests it should not be treated as canonical.
- Is `psy/psysh` intentionally kept for local/manual debugging?
- Should GitHub Actions `actions/*@v5` tags be externally verified in CI planning? This review did not execute the workflow on GitHub.

## Final Assessment

The package tooling is mostly healthy: Composer validation, PHPStan, PHPUnit, Pint, lock consistency, and the PHP CI matrix all look good from local review. The main merge blockers are dependency metadata for direct Carbon imports and documentation drift, especially `docs.md`, which still describes the old validation/key-mapping model and can steer both users and agents toward incorrect behavior. `AGENTS.md` is concise and aligned with the current system; use it as the source to refresh the longer docs and stale logs.
