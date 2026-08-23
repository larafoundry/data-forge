# GitHub Actions Node 24 Compatibility

## Status

Applied.

## Summary

Updated GitHub Actions workflow dependencies to versions compatible with the GitHub Actions Node.js 24 runtime.

## Motivation

GitHub Actions reported deprecation warnings for actions running on Node.js 20. GitHub will force JavaScript actions to Node.js 24 by default starting June 2, 2026.

## Change

Updated workflow actions:

```text
actions/checkout@v4 -> actions/checkout@v5
actions/cache@v4 -> actions/cache@v5
actions/cache/restore@v4 -> actions/cache/restore@v5
actions/cache/save@v4 -> actions/cache/save@v5
```

## Benefit

The CI workflow no longer depends on deprecated Node.js 20 action runtimes for checkout and cache actions.
