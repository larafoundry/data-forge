# Projected Root Array Rule

## Bug

The canonical validation projection could turn an already hydrated nested DTO
object into an array-shaped validation view before Laravel evaluated root-level
shape rules. That allowed a rule such as `child => array` to pass for a DTO
object, even though the raw boundary value was not an array.

## Reproduction

`tests/Unit/Validation/ProjectedRootArrayRuleTest.php`

```php
ProjectedRootArrayParentPocDto::fromArray([
    'child' => new ProjectedRootArrayChildPocDto('Ada'),
]);
```

Parent rule:

```php
'child' => 'array'
```

## Fix

Added a raw-shape rule phase so root-level `array`, `list`, and
`required_array_keys` rules validate the raw canonical input before projected
dotted-rule validation runs.

## Verification

- `composer phpstan`
- `composer test`
- `composer pint`
