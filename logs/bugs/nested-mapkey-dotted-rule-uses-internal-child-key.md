# Nested MapKey Dotted Rule Uses Internal Child Key

## Bug

A parent DTO custom rule such as `child.firstName => required` is evaluated against the raw nested input key `child.firstName` before nested DTO hydration. If the child DTO maps that property with `#[MapKey('first_name')]`, valid input using `child.first_name` is rejected before the child validator can apply its own key map.

## Reproduction

`tests/Unit/Validation/NestedMapKeyDottedRuleTest.php`

The POC builds a parent DTO with a nested child DTO whose `firstName` property is mapped from `first_name`, then submits:

```php
[
    'child' => [
        'first_name' => 'Ada',
    ],
]
```

## Expected

The parent dotted rule should respect the nested child DTO's `MapKey`; the DTO should hydrate successfully and expose `child->firstName === 'Ada'`.

## Before Fix

Validation fails on the current code because the pre-hydration rule is still checked against `child.firstName`.

A direct reproducer with minimal validation stubs produced:

```text
ValidationException errors={"child.firstName":["The child.firstName field is required."]}
```

## Fix

Parent pre-hydration validation now leaves dotted rules below nested DTO and `ArrayOf` collection roots for nested hydration. The nested validator applies the child DTO's own `MapKey` mapping.

## Regression

- `tests/Unit/Validation/NestedMapKeyDottedRuleTest.php`
- Target command: `vendor/bin/phpunit tests/Unit/Validation/NestedMapKeyDottedRuleTest.php`
