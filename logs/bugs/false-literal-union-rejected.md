# False Literal Union Rejected

## Bug

DTO fields typed with a PHP literal-false union, for example `string|false`, reject the valid value `false` during runtime type validation.

## Reproduction

`tests/Feature/DtoPipeline/FalseLiteralUnionTypeTest.php`

The POC DTO declares:

```php
public function __construct(public readonly string|false $value) {}
```

and calls `fromArray(['value' => false])`.

## Expected

The value `false` is part of the declared PHP union type and should be accepted.

## Before Fix

`DtoInspector::checkNamedType()` normalizes PHP booleans to `bool`, then compares that to the reflected literal type name `false`, so the type check returns false and validation rejects the input.

A direct inspector reproducer returned:

```text
bool(false)
```

for `isTypeAcceptedForKey('value', false)`.

## Fix

`DtoInspector::checkNamedType()` now handles literal `false` and `true` builtin reflection names before falling back to normalized builtin type comparison.

## Regression

- `tests/Feature/DtoPipeline/FalseLiteralUnionTypeTest.php`
- Target command: `vendor/bin/phpunit tests/Feature/DtoPipeline/FalseLiteralUnionTypeTest.php`
