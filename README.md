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
- Composer

## Installation

Data Forge has not published a tagged release or Packagist package yet. Install
the current development version directly from this GitHub repository by adding
a VCS repository to your application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/larafoundry/data-forge"
        }
    ]
}
```

Then require the package from the long-lived `master` branch:

```bash
composer require axiom/data-forge:dev-master
```

Pin a commit in production until a SemVer release is available.

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
That same trust boundary applies when a nested property or `#[ArrayOf]` item is
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
traversal semantics are ambiguous at the data boundary. Use `array` for raw
arrays, or `Illuminate\Support\Collection` with `#[ArrayOf]` for typed nested
DTO collections.

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

## Nested DTOs

Nested DTO values are hydrated through Data Forge's validator and hydrator
pipeline. A custom `fromArray()` override is treated as a root-level public
entry point and is not called when that DTO appears as a nested property or an
`#[ArrayOf]` item. Keep transforms required for nested usage in input that is
compatible with the constructor, validation rules, or supported casting
metadata.

Directly constructing a nested DTO with `new ChildDto(...)` also does not run
validation by itself. Treat constructors as trusted-data APIs; use a root DTO
entry point when array input needs boundary validation before nested DTO
construction.

If a caller passes an already-instantiated nested DTO or `#[ArrayOf]` item of
the expected class into `fromArray()`, Data Forge preserves that object as
trusted input. It does not rerun the child DTO's static `rules()` for that
instance. Validate untrusted nested data before instantiating the child DTO, or
pass the nested payload as an array so the normal boundary validation pipeline
can run.

For `#[ArrayOf]` nested DTO collections, validation errors are aggregated across
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

- `DataForgeException`
- `InspectionException`
- `InstantiationException`
- `ValidationException`

Catch `DataForgeException` to handle all package-specific failures, or catch a
more specific subtype when you need targeted recovery.

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
