# Unsupported Validation Rule Leaks

## Problem

Validation rule normalization accepted every `Stringable` object, including
ordinary value objects that are not Laravel rule builders. Those objects reached
Laravel's validator as rule strings and could throw framework exceptions such as
`BadMethodCallException` instead of Data Forge's `InspectionException`.

Database-backed string rules such as `exists:users,id` and `unique:users,email`
also reached the default validator, which has no presence verifier, and leaked
`RuntimeException`.

## Expected

Unsupported validation rule configuration must fail early with
`InspectionException`. Stringable compatibility is limited to explicit Laravel
stringable rule-builder classes, and DB-backed validation requires a separate
validation factory integration decision.
