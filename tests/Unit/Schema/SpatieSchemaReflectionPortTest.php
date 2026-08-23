<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SpatieSchemaReflectionPortTest extends TestCase
{
    public function test_schema_records_promoted_and_public_property_defaults(): void
    {
        $schema = DtoSchemaCompiler::compile(SpatieSchemaDefaultsFixtureDto::class);

        $this->assertSame([
            'locale',
            'name',
            'nickname',
            'publicDefault',
            'requiredPublic',
            'role',
        ], $schema->acceptedKeys());
        $this->assertSame(['name', 'requiredPublic'], $schema->requiredKeys());

        $role = $schema->field('role');
        $this->assertNotNull($role);
        $this->assertFalse($role->required);
        $this->assertTrue($role->hasDefault);
        $this->assertSame('reader', $role->defaultValue);
        $this->assertNotNull($role->property);
        $this->assertNotNull($role->parameter);

        $publicDefault = $schema->field('publicDefault');
        $this->assertNotNull($publicDefault);
        $this->assertFalse($publicDefault->required);
        $this->assertTrue($publicDefault->hasDefault);
        $this->assertSame('public value', $publicDefault->defaultValue);
        $this->assertNotNull($publicDefault->property);
        $this->assertNull($publicDefault->parameter);

        $nickname = $schema->field('nickname');
        $this->assertNotNull($nickname);
        $this->assertFalse($nickname->required);
        $this->assertTrue($nickname->nullable);
        $this->assertTrue($nickname->hasDefault);
        $this->assertNull($nickname->defaultValue);
    }

    public function test_schema_records_non_promoted_constructor_parameter_metadata(): void
    {
        $schema = DtoSchemaCompiler::compile(SpatieSchemaParameterFixtureDto::class);

        $this->assertSame(['name', 'suffix'], $schema->acceptedKeys());
        $this->assertSame(['name'], $schema->requiredKeys());

        $name = $schema->field('name');
        $this->assertNotNull($name);
        $this->assertNull($name->property);
        $this->assertNotNull($name->parameter);
        $this->assertSame('name', $name->parameter->getName());

        $suffix = $schema->field('suffix');
        $this->assertNotNull($suffix);
        $this->assertFalse($suffix->required);
        $this->assertTrue($suffix->hasDefault);
        $this->assertSame('!', $suffix->defaultValue);
    }

    public function test_schema_records_flat_mapping_and_arrayof_attributes(): void
    {
        $schema = DtoSchemaCompiler::compile(SpatieSchemaMappedCollectionFixtureDto::class);

        $this->assertSame([
            'displayName' => 'display_name',
            'members' => 'members',
        ], $schema->keyMap());

        $displayName = $schema->field('displayName');
        $this->assertNotNull($displayName);
        $this->assertSame('display_name', $displayName->inputKey);

        $members = $schema->field('members');
        $this->assertNotNull($members);
        $this->assertSame(SpatieSchemaMemberFixtureDto::class, $members->arrayItemClass);
    }

    public function test_schema_rejects_missing_arrayof_item_classes(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Array item class');

        DtoSchemaCompiler::compile(SpatieSchemaMissingArrayOfClassFixtureDto::class);
    }
}

final class SpatieSchemaDefaultsFixtureDto
{
    use AsDto;

    public string $requiredPublic;

    public string $publicDefault = 'public value';

    public ?string $nickname = null;

    public function __construct(
        public readonly string $name,
        public readonly string $role = 'reader',
        public readonly ?string $locale = null,
    ) {}
}

final class SpatieSchemaParameterFixtureDto
{
    use AsDto;

    public function __construct(string $name, string $suffix = '!')
    {
        unset($name, $suffix);
    }
}

final class SpatieSchemaMappedCollectionFixtureDto
{
    use AsDto;

    #[MapKey('display_name')]
    public string $displayName;

    /**
     * @param  Collection<int, SpatieSchemaMemberFixtureDto>  $members
     */
    public function __construct(
        #[ArrayOf(SpatieSchemaMemberFixtureDto::class)]
        public readonly Collection $members,
    ) {}
}

final class SpatieSchemaMemberFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class SpatieSchemaMissingArrayOfClassFixtureDto
{
    use AsDto;

    public function __construct(
        #[ArrayOf('Tests\\Unit\\Schema\\SpatieSchemaDoesNotExist')]
        public readonly Collection $items,
    ) {}
}
