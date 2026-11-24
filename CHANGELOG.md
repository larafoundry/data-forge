# Changelog

All notable changes to Data Forge are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/). During the
`0.x` series a minor release may contain breaking changes; they are listed
under **Changed** or **Removed**.

## [Unreleased]

## [0.1.0-beta.1]

First public beta.

### Added

- `AsDto` trait with root entry points `fromArray()`, `fromJson()`, and
  `fromArrayable()`.
- `AsStrictInputDto` trait that rejects unknown input keys with
  `UnknownInputKeyException`.
- Automatic `required`, `nullable`, and declared-type validation from
  constructor signatures, plus custom Laravel `rules()` and `messages()`.
- `#[MapKey]` for external input keys, with validation errors reported under
  the external key.
- Nested DTO hydration and typed DTO collections with `#[ArrayOf]`, with
  collection item errors aggregated across all items.
- Casting of backed enums, `DateTime`, `DateTimeImmutable`, `Carbon`, and
  `CarbonImmutable` values.
- Validation attributes `#[Min]`, `#[Max]`, `#[StringLength]`, and
  `#[DateFormat]`.
- `DataForgeException` hierarchy: `ValidationException`,
  `UnknownInputKeyException`, `InspectionException`, and
  `InstantiationException`.
- Support for PHP 8.1 to 8.5 with Illuminate 10.48+, 11, 12, and 13.

### Fixed

- `#[DateFormat]` and `date_format:` casting no longer fill fields missing
  from the format with the current time. `d/m/Y` now always yields midnight,
  matching Laravel's `date_format` validation.
- Validation messages for `#[MapKey]` fields now name the external input key
  (for example "The zip code field…" for `zip_code`) instead of the PHP
  property name.

[Unreleased]: https://github.com/larafoundry/data-forge/compare/v0.1.0-beta.1...HEAD
[0.1.0-beta.1]: https://github.com/larafoundry/data-forge/releases/tag/v0.1.0-beta.1
