# Constraint Attributes Not Enforced

## Status

Fixed.

## Summary

Constraint attributes such as `#[Min]`, `#[Max]`, `#[StringLength]`, and `#[DateFormat]` were metadata only and did not affect validation.

## Actual Behavior

DTOs could be created with values outside attribute-declared constraints.

## Expected Behavior

Attributes that declare DTO constraints should participate in generated validation rules.

## Root Cause

`DtoRuleBuilder` did not inspect constraint attributes when generating Laravel validation rules.

## Fix

`DtoRuleBuilder` now converts supported constraint attributes into Laravel validation rules.

## Regression Coverage

Added a POC regression test proving `#[Min]` and `#[StringLength]` constraints reject invalid input.
