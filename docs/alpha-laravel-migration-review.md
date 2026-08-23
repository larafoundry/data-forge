# Alpha Laravel Migration Review

Date: 2026-06-02

## Verdict

Laravel 10 projects with simple DTO boundaries can move to Data Forge quickly.
Laravel 11/12 projects are not quick migrations yet because current Composer
constraints target Illuminate 10.x only.

Spatie Laravel Data migrations can be fast only when the app uses a small subset
that matches Data Forge's explicit boundary contract. Spatie-heavy applications
need redesign rather than a mechanical migration.

## Migration Speed Estimate

- First simple Laravel 10 DTO: 15-30 minutes.
- 10-30 simple DTOs using arrays/FormRequest validated payloads: 1-3 days.
- Medium Laravel 10 app with nested DTOs, flat mapping, static rules, enum/date
  casting, and typed collections: about 1-2 weeks.
- Laravel 11/12 app: blocked until Composer constraints and CI support are
  expanded, or alpha docs state Laravel 10 only.
- Spatie-heavy app using advanced Spatie features: not a direct migration.

## Fast Migration Cases

Data Forge should feel quick when the existing app already has:

- DTO constructors or public properties as the source of truth;
- array payloads at the boundary;
- FormRequest or service code that can call `SomeDto::fromArray($payload)`;
- flat external key mapping;
- Laravel pipe-string rules or supported explicit Laravel rule objects;
- nested DTOs from arrays;
- `Illuminate\Support\Collection` with explicit item DTO class;
- backed enums and date-like values.

## Slow Or Unsupported Migration Cases

Expect redesign if the current Laravel project depends on:

- Laravel 11/12 install without changing package constraints;
- Spatie `Optional`, `Lazy`, or `DataCollection`;
- request/model/stdClass/magic normalizers;
- paginator/cursor/resource/response wrapping APIs;
- class-level name mappers, snake/camel fallback, aliases, or dotted mapping;
- global casts, custom casts, Eloquent casts, or collection coercion;
- DB-backed validation rules like `exists` and `unique`;
- validation context, container injection, or presence verifier behavior;
- TypeScript generation, Livewire, Inertia, or transformer/resource layers.

## Migration Guide Outline

1. Install
   - Document the exact alpha Composer command.
   - State Laravel/Illuminate compatibility.
   - If the package is not tagged/on Packagist yet, document the VCS/dev-main
     path clearly.

2. Replace the DTO base
   - Replace an app DTO base class or `extends Data` with `use AsDto`.
   - Use an app-composed trait with `AsStrictInputDto` if strict input is the
     project convention.

3. Creation
   - Use `fromArray(array $payload)` as the main boundary entry point.
   - Use `fromJson()` and `fromArrayable()` only at the root.
   - Do not expect request/model/stdClass/magic normalizers.

4. Mapping
   - Replace flat input mapping with `#[MapKey('external_key')]`.
   - Treat the mapped external key as the only accepted input key.
   - Do not rely on canonical aliases or automatic snake/camel conversion.

5. Validation
   - Move supported validation into static `rules()` and `messages()`.
   - Keep rules as pipe strings, flat arrays, or supported Laravel rule objects.
   - Avoid closures, nested rule arrays, DB-backed `exists`/`unique`, and
     container/context-dependent validation in the current alpha scope.

6. Nested DTOs and collections
   - Nested DTOs hydrate through Data Forge's canonical pipeline.
   - Custom child `fromArray()` overrides are root entry points only.
   - Use `Illuminate\Support\Collection` plus `#[ArrayOf(FooDto::class)]` for
     typed nested DTO collections.
   - Keep PHPDoc item types for static analysis, but do not expect runtime item
     inference from PHPDoc.

7. Casting
   - Use supported backed enum and date-like object casting.
   - Do not expect broad scalar coercion, custom/global casts, or collection
     coercion.

8. Test fixtures
   - Keep test data explicit with arrays or application-local fixture helpers.

## Migration Decision

Data Forge is attractive for Laravel teams that want a small, strict data
boundary package with explicit contracts. It is not currently a drop-in
replacement for Spatie Laravel Data as a full product ecosystem.
