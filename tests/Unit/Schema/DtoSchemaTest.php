<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use Axiom\DataForge\Schema\TypeSpec;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class DtoSchemaTest extends TestCase
{
    public function test_input_key_falls_back_to_the_field_name(): void
    {
        $schema = DtoSchemaCompiler::compile(SchemaFixtureDto::class);

        $this->assertSame('display_name', $schema->inputKey('displayName'));
        $this->assertSame('name', $schema->inputKey('name'));
        $this->assertSame('missing', $schema->inputKey('missing'));
    }

    public function test_fields_carry_property_type_and_item_metadata(): void
    {
        $schema = DtoSchemaCompiler::compile(SchemaFixtureDto::class);

        $this->assertSame('displayName', $schema->field('displayName')?->property?->getName());
        $this->assertInstanceOf(TypeSpec::class, $schema->field('children')?->type);
        $this->assertSame(SchemaChildFixtureDto::class, $schema->field('children')?->arrayItemClass);
        $this->assertNull($schema->field('missing'));
    }
}

final class SchemaFixtureDto
{
    use AsDto;

    #[MapKey('display_name')]
    public ?string $displayName = null;

    /**
     * @param  Collection<int, SchemaChildFixtureDto>|null  $children
     */
    public function __construct(
        public readonly string $name,
        public readonly ?Collection $children = null,
    ) {}
}

final class SchemaChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}
