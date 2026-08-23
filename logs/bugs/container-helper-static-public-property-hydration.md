# Container Helper Static Public Property Hydration

## Bug

`ContainerHelper::makeInstance()` skipped static properties when assigning
input data, but still inspected static public properties in the final
uninitialized-property pass. DTOs with static public metadata could fail
hydration even though static properties are not DTO input fields.

## Reproduction

`tests/Unit/Schema/DtoSchemaCompilerTest.php`

```php
final class StaticPublicMetadataPocDto
{
    use AsDto;

    public static string $schemaVersion;

    public function __construct(public readonly string $name) {}
}
```

```php
StaticPublicMetadataPocDto::fromArray(['name' => 'Ada']);
```

## Fix

Skip static properties in both `ContainerHelper` property assignment and
uninitialized-property checks.

## Verification

- `composer phpstan`
- `composer test`
- `composer pint`
