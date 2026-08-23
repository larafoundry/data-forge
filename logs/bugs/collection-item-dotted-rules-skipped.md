# Collection Item Dotted Rules Skipped

## Status

Fixed.

## Summary

Parent DTO dotted validation rules for `#[ArrayOf]` collection items were skipped when an item was already an instance of the declared item DTO class.

## Reproduction

`tests/Unit/Validation/CollectionItemDottedRulesTest.php`

- `test_collection_dotted_rule_validates_already_hydrated_item_instance`

The parent DTO declares `children.*.name => required|min:3` and receives a collection item as an already-hydrated DTO instance with `name = "Al"`.

## Expected Behavior

The parent dotted rule should still be enforced and report the error at `children.0.name`.

## Root Cause

`Validator::hydrateDtoCollection()` short-circuited on `instanceof $class` before applying item rules forwarded from the parent DTO.

## Fix

Already-hydrated collection item DTO instances are now projected into validation data and checked against forwarded parent item rules before being added to the collection.

## Verification

- `vendor/bin/phpunit tests/Unit/Validation/CollectionItemDottedRulesTest.php`
