# Contributing to Data Forge

Thank you for helping improve Data Forge.

## Before You Start

- Search existing issues and discussions before opening a new one.
- Use the issue forms for bugs, features, and maintenance tasks.
- Discuss breaking public API changes before implementation.
- Do not report security vulnerabilities in public issues; follow
  [SECURITY.md](SECURITY.md) instead.

## Branches and Pull Requests

`master` is the only long-lived branch and the source of truth. Create a short-
lived branch using one of these prefixes:

- `feature/`
- `fix/`
- `docs/`
- `refactor/`
- `chore/`
- `hotfix/` when an urgent production fix is required

Do not create `develop` or `release/*` branches. Open every pull request against
`master`, keep its history linear, and resolve all review conversations before
merge. The maintainer uses squash merges, so write a clear PR title and body.

## Local Setup and Checks

Install dependencies with Composer:

```bash
composer install
```

Before opening or updating a pull request, run:

```bash
composer audit
composer phpstan
composer test
composer pint:test
```

Use `composer pint` to fix formatting. Add focused tests for behavior changes
and update public documentation when a contract or example changes.

## Releases

Releases are created by tagging a SemVer version on a tested commit from
`master` and publishing the corresponding GitHub Release. Contributors should
not create release branches or version tags.
