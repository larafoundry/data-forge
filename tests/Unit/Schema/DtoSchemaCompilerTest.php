<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use PHPUnit\Framework\TestCase;

final class DtoSchemaCompilerTest extends TestCase
{
    public function test_static_public_properties_are_not_dto_input_fields(): void
    {
        $inspector = new DtoInspector(StaticPublicMetadataFixtureDto::class);

        $this->assertSame(['name'], $inspector->getAcceptedKeys());
        $this->assertSame(['name'], $inspector->getRequiredKeys());
    }

    public function test_static_public_property_does_not_break_hydration(): void
    {
        $dto = StaticPublicMetadataFixtureDto::fromArray([
            'name' => 'Ada',
        ]);

        $this->assertSame('Ada', $dto->name);
    }

    public function test_compiler_extracts_constructor_and_public_property_field_metadata(): void
    {
        $schema = DtoSchemaCompiler::compile(SchemaFieldMetadataFixtureDto::class);

        $this->assertSame(['displayName', 'email', 'name'], $schema->acceptedKeys());
        $this->assertSame(['email', 'name'], $schema->requiredKeys());
        $this->assertSame([
            'displayName' => 'display_name',
            'email' => 'email',
            'name' => 'name',
        ], $schema->keyMap());

        $displayName = $schema->field('displayName');

        $this->assertNotNull($displayName);
        $this->assertFalse($displayName->required);
        $this->assertTrue($displayName->nullable);
        $this->assertTrue($displayName->hasDefault);
        $this->assertNull($displayName->defaultValue);
    }

    public function test_compiler_stores_dto_rules_and_messages_on_the_schema(): void
    {
        $schema = DtoSchemaCompiler::compile(SchemaContractFixtureDto::class);

        $this->assertSame([
            'name' => 'required|min:3',
        ], $schema->rules);
        $this->assertSame([
            'name.required' => 'Name is required.',
        ], $schema->messages);
    }

    public function test_compiler_rejects_duplicate_mapped_input_keys_as_inspection_failure(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('map to the same input key');

        DtoSchemaCompiler::compile(DuplicateMappedInputSchemaFixtureDto::class);
    }

    public function test_compiler_rejects_mapped_key_colliding_with_unmapped_field(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('map to the same input key');

        DtoSchemaCompiler::compile(MappedAndUnmappedInputCollisionSchemaFixtureDto::class);
    }

    public function test_compiler_rejects_non_array_rules_hook_result(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('rules()');

        DtoSchemaCompiler::compile(NonArrayRulesSchemaFixtureDto::class);
    }

    public function test_compiler_rejects_non_array_messages_hook_result(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('messages()');

        DtoSchemaCompiler::compile(NonArrayMessagesSchemaFixtureDto::class);
    }
}

final class StaticPublicMetadataFixtureDto
{
    use AsDto;

    public static string $schemaVersion;

    public function __construct(public readonly string $name) {}
}

final class SchemaFieldMetadataFixtureDto
{
    use AsDto;

    #[MapKey('display_name')]
    public ?string $displayName = null;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}

final class SchemaContractFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    public static function rules(): array
    {
        return [
            'name' => 'required|min:3',
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
        ];
    }
}

final class DuplicateMappedInputSchemaFixtureDto
{
    use AsDto;

    #[MapKey('shared')]
    public string $first;

    #[MapKey('shared')]
    public string $second;
}

final class MappedAndUnmappedInputCollisionSchemaFixtureDto
{
    use AsDto;

    #[MapKey('name')]
    public string $firstName;

    public string $name;
}

final class NonArrayRulesSchemaFixtureDto
{
    use AsDto;

    public function __construct(public readonly ?string $name = null) {}

    public static function rules()
    {
        return 'name|required';
    }
}

final class NonArrayMessagesSchemaFixtureDto
{
    use AsDto;

    public function __construct(public readonly ?string $name = null) {}

    public static function messages()
    {
        return 'name is required';
    }
}
