# Data Handling Rules

Data Forge is a data boundary package. Its job is to turn unknown input into
typed DTOs, validate shape and meaning, and fail loudly when the input does not
match the contract.

The package must behave like a good Unix/Linux tool: small surface area, clear
contracts, deterministic output, plain errors, and no hidden magic.

## Principles

### 1) The Contract Is The Source Of Truth

DTO constructor types, public property types, attributes, validation rules, and
PHPDoc generics define the contract.

Implementation must follow that contract exactly. Do not add defensive behavior
that contradicts or weakens it.

Custom validation rule objects should use Laravel's
`Illuminate\Contracts\Validation\ValidationRule` contract. The deprecated
`Illuminate\Contracts\Validation\Rule` contract remains accepted only as an
explicit Laravel compatibility path, and `Stringable` rule objects are accepted
for Laravel's stringable rule builders.

If the contract says `string`, invalid input must fail. If the contract says
`Collection<int, UserDto>`, the package must hydrate a collection of `UserDto`
objects or report why it cannot.

### 2) Fail Loudly, Fail Early

Bad data must stop at the boundary.

Do not catch data integrity errors and continue. Do not return partially
hydrated DTOs. Do not silently drop fields that are required by the contract.
Do not invent default values for missing required data.

Every failure should point to the exact field when possible. Nested data must
use clear paths such as `person.email` or `people.0.name`.
Nested DTO collections should aggregate item validation errors instead of
stopping at the first invalid item.

### 3) Never Guess

The package must never infer intent from multiple possible sources.

No probing aliases. No guessing between `user_id`, `userId`, and `id`. No
fallback chains. No "best effort" hydration. No compatibility behavior unless it
is explicitly declared in the DTO contract, such as `#[MapKey]`.

If the source key, target type, nested DTO class, or collection item type is not
clear, throw a package exception with a clear message.

### 4) One Field, One Meaning

Each DTO field has one name, one target type, and one source.

Mapping is allowed only when declared explicitly with a supported attribute.
The mapped key is then the source of truth for that field. The package should
not also accept hidden aliases for the same value.

Unknown input key policy is explicit at the DTO boundary:

- `AsDto` is flexible and ignores unknown input keys.
- `AsStrictInputDto` rejects unknown input keys for that DTO class.

Strictness is local to the DTO being hydrated. A strict parent must not make a
nested DTO strict, and a flexible parent must not make a strict child flexible.
Strict unknown-key errors are already expressed in raw input keys and must not
be translated as canonical property paths.

### 5) Boundary-Only Transformation

Parsing, casting, validation, and normalization belong inside the boundary
pipeline: input array, inspector, validator, caster, and DTO constructor.

After a DTO exists, its values should already be valid. Core code must not need
extra "just in case" cleanup.

Allowed examples:

- JSON or Arrayable root input to a plain input array through explicit root APIs
- backed enum value to enum instance
- date string to configured date object
- nested array to nested DTO
- array of nested records to typed collection when `#[ArrayOf]` declares the
  item class

Forbidden examples:

- trimming or coercing values deep in business logic
- accepting multiple field names for the same value
- converting unknown structures by guessing shape
- returning raw arrays where the contract requires DTO objects

### 6) Types Must Stay Useful To Humans And Tools

Runtime type safety and static type hints must move together.

When PHP cannot express the full type directly, PHPDoc must complete the
contract. For example, collection DTO fields must declare both the runtime type
and item type:

```php
use Axiom\DataForge\Attributes\ArrayOf;
use Illuminate\Support\Collection;

/**
 * @param Collection<int, UserDto> $users
 */
public function __construct(
    #[ArrayOf(UserDto::class)]
    public readonly Collection $users,
) {}
```

The runtime type tells PHP what the property is. The attribute tells Data Forge
how to hydrate it. The PHPDoc tells phpstan and IDEs what each item is.
Runtime hydration must not infer collection item classes from PHPDoc alone.

`AsDto` intentionally does not support `iterable` fields. Use `array` when the
contract is a raw PHP array, or `Collection` with `#[ArrayOf]` when the contract
is a typed nested DTO collection.

### 7) Reflection Must Be Deterministic

Reflection code must inspect declared DTO structure, not runtime accidents.

Prefer explicit constructor parameters and public properties. If a field cannot
be inspected safely, fail with an inspection exception instead of continuing
with a partial model.

Reflection order and metadata lookup must not change behavior unpredictably.

### 8) Validation Errors Are Data

Validation errors are part of the package contract.

Keep them structured, stable, and specific. Do not collapse nested failures into
generic messages. Do not hide lower-level validation errors when hydrating
nested DTOs or DTO collections.

### 9) Factories Must Respect The Same Contract

Factories are not allowed to bypass rules that normal hydration must obey.

Generated values must be valid for the declared DTO contract. Nested DTOs and
DTO collections generated by factories must still pass through the same
validation and hydration path as normal input.

### 10) Phpstan Is A Gate, Not A Suggestion

Phpstan findings are contract failures until proven otherwise.

Do not silence phpstan to make implementation easier. Prefer precise PHPDoc,
clear generics, stricter local types, and smaller helper methods.

### 11) Keep The Package Small And Honest

Do one thing well: convert input into valid typed data objects.

Avoid broad compatibility layers, hidden registries, global state, or behavior
that depends on application-specific conventions. A package user should be able
to read the DTO and know exactly what input is accepted and what object will be
created.

## Hard Bans

- No guessing source keys.
- No hidden aliases.
- No fallback chains for required data.
- No silent casts outside the declared casting boundary.
- No partial DTOs.
- No swallowed validation, inspection, or instantiation errors.
- No raw arrays where the contract promises typed DTOs or typed collections.
- No untyped collection item handling for nested DTO arrays.
- No phpstan ignores for data contract problems.
- No "compatibility" behavior unless the DTO declares it explicitly.

## Change Checklist

Before changing data handling behavior:

1. Define the contract first.
2. Make runtime behavior match the contract.
3. Make phpstan and IDE type hints understand the contract.
4. Add tests for success, invalid input, and nested error paths when relevant.
5. Run phpstan.
6. Run the test suite.
7. Run Pint last.
