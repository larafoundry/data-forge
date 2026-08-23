# Alpha Logic Review

Date: 2026-06-02

## Verdict

The core `fromArray()` logic is broadly alpha-ready. The raw/canonical boundary
is clear, validation errors return to external input keys at the root, nested
DTOs use the canonical validator/hydrator path, and collection validation
aggregates item errors.

## Core Pipeline Assessment

The current pipeline matches the intended contract:

```text
raw input
-> strict unknown-key preflight
-> normalize external keys to canonical DTO keys
-> validate raw/projected shape
-> hydrate nested DTOs, collections, enums, and date-like values
-> validate typed data
-> instantiate DTO from canonical data
```

Important boundaries are mostly honored:

- Non-strict `fromArray()` ignores unknown root raw input keys.
- Strict DTOs reject unknown raw keys before projection or hydration can drop
  them.
- `#[MapKey]` external keys are the accepted input keys; canonical property names
  are not aliases.
- Root validation errors are mapped back to external input keys.
- Unknown-key errors are already raw input-key errors and are not translated.
- Nested DTO hydration does not call custom child `fromArray()` overrides.

## Remaining Non-Blocking Logic Risks

- Dotted child rules can mask a structural type error. For example, scalar input
  for a nested DTO with a parent rule like `child.name` may report
  `child.name required` instead of `child` being an invalid DTO record.
- Some `#[ArrayOf]` declaration mistakes surface as `ValidationException`.
  Because these are DTO contract/configuration problems, `InspectionException`
  would better match the project exception taxonomy.
- Schema cache behavior assumes DTO `rules()` and `messages()` are stable static
  declarations. That is acceptable for alpha if documented, but dynamic
  runtime-dependent rules can become stale in long-running processes.

## Alpha Decision

Logic is strong enough for an alpha focused on explicit array input, validation,
mapping, hydration, and casting.
