# Changelog

All notable changes to Data Forge are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/). During the
`0.x` series a minor release may contain breaking changes; they are listed
under **Changed** or **Removed**.

## [Unreleased]

### Added

- Support for PHP 8.6.
- DTO collection item types are read from traditional PHPDoc on `array` and
  `Collection` fields: `Collection<int, UserDto>`, `Collection<UserDto>`,
  `UserDto[]`, `Collection|UserDto[]`, `array<int, UserDto>`, `self[]`, and
  nullable forms, from constructor `@param` or property `@var` tags (including
  tags declared in traits). Parsing uses `phpdocumentor/reflection-docblock`
  ^6.0 and `phpdocumentor/type-resolver` ^2.0. `opcache.save_comments` must
  stay enabled.
- `array` fields with a DTO item type hydrate into a list of DTOs, and
  `Collection` subclasses hydrate into the declared class.

### Removed

- `#[ArrayOf]`. Declare the item type in PHPDoc instead:
  `@param Collection<int, UserDto> $members`.

### Changed

- When the PHPDoc of an `array` or `Collection` field names a DTO anywhere, it
  must be one supported form; otherwise `InspectionException` is thrown. This
  covers unknown classes, malformed tags, PHPStan-only types such as
  `list<UserDto>`, non-int keys, nullable items, nested lists, DTO lists mixed
  with other types, conflicting `@param`/`@var` tags, and union native types.
  Stripped doc comments (`opcache.save_comments=0`) also throw. `@phpstan-*`
  and `@psalm-*` tags are not read.
- A `Collection` subclass that cannot be built from the item list throws
  `InstantiationException`.

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
- A misconfigured `#[ArrayOf]` now throws `InspectionException` when the DTO
  is inspected. Before, it reached callers as a `ValidationException`. This
  covers a field not typed as `Collection` and an item class that is not a DTO.
- Rules that target a single collection item by index, such as
  `'items.0' => 'array'`, no longer crash with a `TypeError`.
- `#[DateFormat]` formats that contain an escaped `\|` or `\!` now also reset
  fields missing from the format, instead of using the current time.
- DNF types such as `(Countable&Stringable)|int` now check every member of the
  intersection instead of rejecting all objects.

[Unreleased]: https://github.com/larafoundry/data-forge/compare/v0.1.0-beta.1...HEAD
[0.1.0-beta.1]: https://github.com/larafoundry/data-forge/releases/tag/v0.1.0-beta.1
