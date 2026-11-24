# Data Forge

Data Forge is a PHP 8.1+ package for building typed DTOs from arrays and
validating their input in applications using Illuminate 10 through 13.

It uses reflection to inspect DTO constructors and public properties, Laravel's
validator for rules and messages, and a focused hydration pipeline for nested
DTOs, collections, enums, and date-like objects.

## Features

- Create typed DTOs from arrays with `AsDto::fromArray()`, `fromJson()`, or
  `fromArrayable()`
- Validate required fields and declared property types automatically
- Add custom Laravel validation rules and messages on the DTO class
- Map external input keys to internal DTO property names with `#[MapKey]`
- Opt into strict unknown-key rejection with `AsStrictInputDto`
- Auto-cast common values such as enums, `DateTime`, `DateTimeImmutable`, Carbon,
  and Carbon immutable instances from strings
- Catch package-specific failures through the `DataForgeException` hierarchy

## Requirements

- PHP 8.1 or higher
- `illuminate/support`, `illuminate/validation`, `illuminate/container`, and
  `illuminate/translation` 10.48+, 11, 12, or 13
- Composer
- the `tokenizer` extension (enabled by default in PHP)
- `opcache.save_comments` left enabled (the PHP default), because collection
  item types are read from PHPDoc

## Project Status

Data Forge is in **beta**. The behavior documented here is covered by tests and
intended to stay stable, but the public API may still change before `1.0.0`.
Breaking changes during the `0.x` series are listed in
[CHANGELOG.md](CHANGELOG.md). Pin a minor version (for example `^0.1`) and
review the changelog before upgrading.

## Installation

Install the beta from Packagist:

```bash
composer require axiom/data-forge:^0.1@beta
```

If your application's `minimum-stability` is already `beta` or lower, the
`@beta` flag is not needed.

For local development in this repository:

```bash
composer install
```

## Quick Start

Add the `AsDto` trait to a DTO class:

```php
<?php

use Axiom\DataForge\Concerns\AsDto;

final class UserDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly ?string $email = null,
    ) {}
}
```

Create the DTO from an array:

```php
$user = UserDto::fromArray([
    'name' => 'John Doe',
    'age' => 30,
    'email' => 'john@example.com',
]);

echo $user->name; // John Doe
```

Nullable constructor arguments may be omitted:

```php
$user = UserDto::fromArray([
    'name' => 'Jane Doe',
    'age' => 28,
]);

var_dump($user->email); // null
```

Root JSON objects and `Illuminate\Contracts\Support\Arrayable` instances can be
normalized through explicit root entry points:

```php
$user = UserDto::fromJson('{"name":"Jane Doe","age":28}');
$user = UserDto::fromArrayable($arrayable);
```

These helpers normalize to an array and then use the same `fromArray()`
validation and hydration pipeline. They do not enable request, model, stdClass,
or magic `from*` normalizers.

Validation only runs through these explicit root entry points. Calling a DTO
constructor directly with `new` does not validate input:

```php
$trusted = new UserDto('Jane Doe', 28);
```

Use constructors for already-trusted canonical data. Use `fromArray()`,
`fromJson()`, or `fromArrayable()` when untrusted input must be validated.
That same trust boundary applies when a nested property or collection item is
already an instance of the expected DTO class.

## Unknown Input Keys

`AsDto` is flexible by default. Unknown input keys are ignored:

```php
$user = UserDto::fromArray([
    'name' => 'Jane Doe',
    'age' => 28,
    'role' => 'ignored',
]);
```

Use `AsStrictInputDto` when a DTO should reject unknown input keys:

```php
<?php

use Axiom\DataForge\Concerns\AsStrictInputDto;

final class StrictUserDto
{
    use AsStrictInputDto;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}

StrictUserDto::fromArray([
    'name' => 'Jane Doe',
    'age' => 28,
    'role' => 'rejected',
]);
```

Strictness is local to the DTO class being hydrated. A strict parent does not
make nested DTOs strict, and a flexible parent does not make a strict child
flexible.

To make strict DTOs your application convention, compose your own trait:

```php
<?php

use Axiom\DataForge\Concerns\AsStrictInputDto;

trait AsAppDto
{
    use AsStrictInputDto;
}
```

`AsDto` does not support `iterable` typed fields because item type and
traversal semantics are ambiguous at the data boundary. Use `array` or
`Illuminate\Support\Collection` with a DTO item type in PHPDoc for typed nested
DTO collections, and without one for raw data.

## Validation Rules

DTOs may define Laravel validation rules and custom messages:

```php
<?php

use Axiom\DataForge\Concerns\AsDto;
use Illuminate\Validation\Rule;

final class RegisterUserDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly string $email,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
            'age' => 'required|integer|min:18',
            'email' => ['required', 'email', Rule::notIn(['blocked@example.com'])],
        ];
    }

    public static function messages(): array
    {
        return [
            'age.min' => 'You must be at least :min years old.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
```

Rules are accepted as a pipe-delimited string, a flat array of strings, or
supported Laravel rule objects such as `Rule::in()`, `Rule::notIn()`, and
`Rule::enum()`. For custom rule objects, prefer Laravel's
`Illuminate\Contracts\Validation\ValidationRule` contract. The deprecated
`Illuminate\Contracts\Validation\Rule` contract is accepted for compatibility
with Laravel rule objects that still use it. Closures, nested rule arrays,
Spatie-style validation attributes, and DB-backed `Rule::exists()`/`Rule::unique()`
objects are not currently supported.

Validation errors are thrown as
`Axiom\DataForge\Exceptions\ValidationException`, with structured errors
available on the `$errors` property.

## Validation Attributes

Common constraints can be declared on constructor properties instead of in
`rules()`:

```php
<?php

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Attributes\Max;
use Axiom\DataForge\Attributes\Min;
use Axiom\DataForge\Attributes\StringLength;
use Axiom\DataForge\Concerns\AsDto;

final class MemberDto
{
    use AsDto;

    public function __construct(
        #[Min(18), Max(120)]
        public readonly int $age,

        #[StringLength(min: 3, max: 50)]
        public readonly string $name,

        #[DateFormat('d/m/Y', timezone: 'Asia/Ho_Chi_Minh')]
        public readonly DateTimeImmutable $bornAt,
    ) {}
}
```

- `#[Min(n)]` / `#[Max(n)]` add Laravel `min:` / `max:` rules. On `int` and
  `float` fields they compare numeric values.
- `#[StringLength(min, max)]` adds `min:` and `max:` length rules. The defaults
  are `0` and `255`.
- `#[DateFormat(format, timezone)]` adds a `date_format:` rule and parses the
  value with that exact format into the declared date-like type. Fields that
  are not in the format are reset, not filled from the current time: `d/m/Y`
  always yields midnight. An invalid timezone is reported as an
  `InspectionException`.

Attribute rules are combined with any rules returned by `rules()`.

## Mapped Input Keys

Use `#[MapKey]` when incoming data uses different names than your DTO
properties:

```php
<?php

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;

final class ProfileDto
{
    use AsDto;

    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,

        #[MapKey('email_address')]
        public readonly string $email,
    ) {}
}

$profile = ProfileDto::fromArray([
    'first_name' => 'John',
    'email_address' => 'john@example.com',
]);
```

When a field has `#[MapKey]`, the mapped external key is the accepted input key.
The canonical property name is not treated as an alias. In flexible mode it is
ignored; in strict mode it is rejected as an unknown input key.

Validation errors use the external key, both in the error bag and in the
message text. Nested paths are translated segment by segment:

```php
use Axiom\DataForge\Exceptions\ValidationException;

final class AccountDto
{
    use AsDto;

    public function __construct(
        #[MapKey('user_profile')]
        public readonly ProfileDto $profile,
    ) {}
}

try {
    AccountDto::fromArray([
        'user_profile' => [
            'first_name' => 'John',
            'email' => 'john@example.com', // canonical name, not an accepted key
        ],
    ]);
} catch (ValidationException $e) {
    $e->errors;
    // ['user_profile.email_address' => ['The email address field is required.']]
}
```

The error path is `user_profile.email_address`, never `profile.email`. Sending
the canonical `profile` key at the root fails the same way:
`['user_profile' => ['The user profile field is required.']]`.

## Nested DTOs

Type a property as another DTO to hydrate a nested object. For a list of DTOs,
type the property as `array` or `Illuminate\Support\Collection` and declare the
item type in PHPDoc:

```php
<?php

use Axiom\DataForge\Concerns\AsDto;
use Illuminate\Support\Collection;

final class TeamDto
{
    use AsDto;

    /**
     * @param  Collection<int, UserDto>  $members
     * @param  UserDto[]  $reviewers
     */
    public function __construct(
        public readonly UserDto $owner,
        public readonly Collection $members,
        public readonly array $reviewers,
    ) {}
}

$team = TeamDto::fromArray([
    'owner' => ['name' => 'Ada', 'age' => 36],
    'members' => [
        ['name' => 'Grace', 'age' => 45],
        ['name' => 'Linus', 'age' => 28],
    ],
    'reviewers' => [
        ['name' => 'Barbara', 'age' => 52],
    ],
]);

$team->members;   // Collection of UserDto
$team->reviewers; // list of UserDto
```

The same PHPDoc drives runtime hydration, static analysis, and IDEs, so the item
type is declared once. Data Forge reads traditional PHPDoc with
[phpDocumentor](https://github.com/phpDocumentor/ReflectionDocBlock), which
resolves `use` imports and aliases:

| Native type | Supported PHPDoc | Result |
| --- | --- | --- |
| `Collection` (or a subclass) | `Collection<UserDto>`, `Collection<int, UserDto>`, `UserDto[]`, `array<int, UserDto>`, `Collection\|UserDto[]` | the declared collection class of `UserDto` |
| `array` | `UserDto[]`, `array<UserDto>`, `array<int, UserDto>`, `array\|UserDto[]` | a list of `UserDto` |

- Nullable forms (`?…` or `…|null`) are supported.
- `self[]` refers to the DTO that declares the field, for tree-shaped DTOs.
- A generic may name the field's class or one of its parents or interfaces,
  for example `Enumerable<int, UserDto>` on a `Collection` field.
- Types are read from the constructor's `@param` tag or the property's `@var`
  tag; if both are present they must agree. A `@param` tag must name its
  parameter; a `@var` tag that names a different property is ignored.
- Data Forge trusts phpDocumentor to parse PHPDoc and resolve class names and
  does not patch its results. phpDocumentor resolves PHPDoc in a trait with
  the imports of the class using the trait, so use fully-qualified class names
  in trait PHPDoc. Group imports with sub-namespaces
  (`use App\{Dto\UserDto}`) are not resolved; use `use App\Dto\UserDto;`.
- Item types that are not DTO objects (such as `Collection<int, string>` or
  `class-string<UserDto>[]`) leave the field untouched. Untyped fields are
  never hydrated.
- Default values are written by the DTO author and used as they are; they are
  not hydrated.

PHPStan and Psalm specific tags (`@phpstan-param`, `@psalm-var`, ...) are not
read. When the PHPDoc of an `array` or `Collection` field names a DTO anywhere,
it must be exactly one of the forms above. Anything else throws
`InspectionException` instead of being ignored, for example:

- an unknown class (check the name and its `use` import)
- a malformed tag such as `array<int, UserDto $users`
- `list<UserDto>`, `array{0: UserDto}`, conditional types, or other PHPStan-only types
- keys other than `int` (including `positive-int`), nullable items, nested
  lists such as `UserDto[][]`, or `static[]` and `parent[]`
- a DTO list mixed with other types, such as `UserDto[]|string[]`
- more than one DTO item type, or a union or intersection native type such as `array|Collection`
- more than one `@param` (or `@var`) tag for the same field

A `Collection` subclass whose constructor cannot take the list of items, or
does not keep it, throws `InstantiationException`.

Nested DTO values are hydrated through Data Forge's validator and hydrator
pipeline. A custom `fromArray()` override is treated as a root-level public
entry point and is not called when that DTO appears as a nested property or a
collection item. Keep transforms required for nested usage in input that is
compatible with the constructor, validation rules, or supported casting
metadata.

Directly constructing a nested DTO with `new ChildDto(...)` also does not run
validation by itself. Treat constructors as trusted-data APIs; use a root DTO
entry point when array input needs boundary validation before nested DTO
construction.

If a caller passes an already-instantiated nested DTO or collection item of
the expected class into `fromArray()`, Data Forge preserves that object as
trusted input. It does not rerun the child DTO's static `rules()` for that
instance. Validate untrusted nested data before instantiating the child DTO, or
pass the nested payload as an array so the normal boundary validation pipeline
can run.

For nested DTO collections, validation errors are aggregated across
invalid items so callers can see paths such as `people.0.name` and
`people.1.name` in one error bag.

Union DTO types are not supported. For example, fields such as
`UserDto|AdminDto`, `object|UserDto`, or other unions containing multiple DTO
or object hydration targets make array input ambiguous. Given an input array,
Data Forge cannot safely determine which DTO class should be instantiated,
especially once validation rules, mapped keys, and strict unknown-key checks
are involved. This package keeps that contract explicit rather than guessing
between possible object branches.

## Auto-Casting

Data Forge can cast compatible string values during its validation and
hydration pipeline for supported target types, including:

- backed enums
- `DateTime`
- `DateTimeImmutable`
- `Carbon\Carbon`
- `Carbon\CarbonImmutable`

For example, a DTO constructor argument typed as an enum can receive its backed
string value, and date-like constructor arguments can receive parseable date
strings.

## Exceptions

Package exceptions live under `Axiom\DataForge\Exceptions`:

- `DataForgeException`: base class for every package exception
- `ValidationException`: input failed validation. Errors are available on
  `$errors`, keyed by external input paths such as `members.1.name`.
- `UnknownInputKeyException`: a strict DTO received keys it does not declare.
  It extends `ValidationException`, so existing validation handlers catch it
  too. Its `$errors` keep the exact raw input path.
- `InspectionException`: the DTO class itself is misconfigured (for example an
  unsupported rule value, a bad `#[DateFormat]` timezone, or a collection
  PHPDoc that names an unknown class or an unsupported DTO item form)
- `InstantiationException`: the DTO could not be constructed from validated
  data

Catch `DataForgeException` to handle all package-specific failures, or catch a
more specific subtype when you need targeted recovery.

## Known Limitations

- Validation runs in stages: first the raw input shape, then nested DTOs and
  collections, then root-level types. A payload with errors in more than one
  stage reports only the first failing stage. For example, an invalid nested
  DTO is reported before an invalid root scalar in the same payload.
- Fields without a default and not nullable get Laravel's `required` rule, so
  an empty string `''` or empty array `[]` counts as missing. Give the field a
  default (`array $tags = []`) or make it nullable to accept empty values.
- Closures, nested rule arrays, and DB-backed `Rule::exists()` /
  `Rule::unique()` rules are not supported in `rules()`.
- Union DTO types, `iterable` fields, scalar coercion from strings, and custom
  or global casts are not supported.

## Development

Useful local commands:

```bash
composer audit
composer phpstan
composer test
composer pint:test
```

Before committing, keep the project clean by running the dependency audit,
PHPStan, the test suite, and Laravel Pint.

## License

Data Forge is open-sourced software licensed under the MIT license.
