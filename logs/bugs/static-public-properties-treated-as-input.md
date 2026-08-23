# Static Public Properties Treated As Input

## Bug

`DtoInspector` treats public static properties as DTO input fields because it iterates all public properties without excluding static properties.

## Reproduction

`tests/Unit/Schema/DtoSchemaCompilerTest.php`

The POC DTO declares a constructor input field and public static metadata:

```php
public static string $schemaVersion;

public function __construct(public readonly string $name) {}
```

## Expected

Only instance constructor parameters and instance public properties should be DTO input fields. The accepted and required keys should be `['name']`.

## Before Fix

`DtoInspector::getAcceptedKeys()` and `DtoInspector::getRequiredKeys()` both include `schemaVersion`, which would make otherwise valid payloads fail validation as missing static metadata.

A direct inspector reproducer returned:

```text
accepted: ["name", "schemaVersion"]
required: ["name", "schemaVersion"]
```

## Fix

`DtoInspector` now ignores static properties when building DTO field lists, key maps, reflection properties, type lookups, and attribute lookups for DTO input keys.

## Regression

- `tests/Unit/Schema/DtoSchemaCompilerTest.php`
- Target command: `vendor/bin/phpunit tests/Unit/Schema/DtoSchemaCompilerTest.php`
