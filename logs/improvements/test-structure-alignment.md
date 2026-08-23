# Test Structure Alignment

Moved regression tests out of the temporary bug proof-of-concept namespace and into the
owning test areas:

- pipeline-level DTO behavior under `tests/Feature/DtoPipeline`
- input boundary behavior under `tests/Unit/Input`
- validation behavior under `tests/Unit/Validation`
- schema compilation behavior under `tests/Unit/Schema`
- hydration object construction tests under `tests/Unit/Hydration`

Added focused unit coverage for `InputMapper`, `UnknownInputKeyValidator`, and
`ValidationProjector` so the test tree mirrors the source boundaries more
clearly.

Split PHPUnit into `Unit` and `Feature` suites so the directory boundary can be
used directly from CLI/CI, and removed the package-inapplicable `app` source
include.

Added direct schema read/typing coverage for `DtoInspector` and `TypeSpec`, the
remaining central schema surfaces with meaningful behavior.
