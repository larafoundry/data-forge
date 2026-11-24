<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

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
        $this->assertNotNull($role->property);

        $publicDefault = $schema->field('publicDefault');
        $this->assertNotNull($publicDefault);
        $this->assertFalse($publicDefault->required);
        $this->assertNotNull($publicDefault->property);

        $nickname = $schema->field('nickname');
        $this->assertNotNull($nickname);
        $this->assertFalse($nickname->required);
    }

    public function test_schema_records_non_promoted_constructor_parameter_metadata(): void
    {
        $schema = DtoSchemaCompiler::compile(SpatieSchemaParameterFixtureDto::class);

        $this->assertSame(['name', 'suffix'], $schema->acceptedKeys());
        $this->assertSame(['name'], $schema->requiredKeys());

        $name = $schema->field('name');
        $this->assertNotNull($name);
        $this->assertNull($name->property);

        $suffix = $schema->field('suffix');
        $this->assertNotNull($suffix);
        $this->assertFalse($suffix->required);
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

    public function test_schema_rejects_missing_phpdoc_item_classes(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage(
            "PHPDoc type 'Tests\\Unit\\Schema\\SpatieSchemaDoesNotExist' of field 'items' on "
            .SpatieSchemaMissingItemClassFixtureDto::class.' does not exist; check the class name and its use import'
        );

        DtoSchemaCompiler::compile(SpatieSchemaMissingItemClassFixtureDto::class);
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
        public readonly Collection $members,
    ) {}
}

final class SpatieSchemaMemberFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class SpatieSchemaMissingItemClassFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieSchemaDoesNotExist>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}
}
