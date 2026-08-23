<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Schema\TypeSpec;
use PHPUnit\Framework\TestCase;

final class DtoInspectorTest extends TestCase
{
    public function test_exposes_compiled_schema_through_read_api(): void
    {
        $inspector = new DtoInspector(InspectorFixtureDto::class);

        $this->assertSame(InspectorFixtureDto::class, $inspector->class);
        $this->assertSame(InspectorFixtureDto::class, $inspector->getReflection()->getName());
        $this->assertSame(['children', 'displayName', 'name'], $inspector->getAcceptedKeys());
        $this->assertSame(['name'], $inspector->getRequiredKeys());
        $this->assertSame([
            'name' => 'required|min:3',
        ], $inspector->getRules());
        $this->assertSame([
            'name.required' => 'Name is required.',
        ], $inspector->getMessages());
        $this->assertSame([
            'children' => 'children',
            'displayName' => 'display_name',
            'name' => 'name',
        ], $inspector->getKeyMap());
    }

    public function test_reads_mapping_type_property_and_array_item_metadata(): void
    {
        $inspector = new DtoInspector(InspectorFixtureDto::class);

        $this->assertSame('display_name', $inspector->getMappedKey('displayName'));
        $this->assertSame('name', $inspector->getMappedKey('name'));
        $this->assertSame('missing', $inspector->getMappedKey('missing'));

        $this->assertTrue($inspector->isTypeAcceptedForKey('name', 'Ada'));
        $this->assertFalse($inspector->isTypeAcceptedForKey('name', 123));
        $this->assertFalse($inspector->isTypeAcceptedForKey('missing', 'Ada'));

        $this->assertSame('displayName', $inspector->getReflectionProperty('displayName')?->getName());
        $this->assertNull($inspector->getReflectionProperty('missing'));
        $this->assertSame('name', $inspector->getReflectionPropertyOrFail('name')->getName());

        $this->assertNotNull($inspector->getTypeForKey('name'));
        $this->assertNull($inspector->getTypeForKey('missing'));
        $this->assertInstanceOf(TypeSpec::class, $inspector->getTypeSpecForKey('children'));
        $this->assertNull($inspector->getTypeSpecForKey('missing'));

        $this->assertSame(InspectorChildFixtureDto::class, $inspector->getArrayItemClassForKey('children'));
        $this->assertNull($inspector->getArrayItemClassForKey('missing'));
    }

    public function test_reflection_property_or_fail_reports_unknown_property(): void
    {
        $inspector = new DtoInspector(InspectorFixtureDto::class);

        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage("Property 'missing' does not exist in class 'Tests\Unit\Schema\InspectorFixtureDto'");

        $inspector->getReflectionPropertyOrFail('missing');
    }
}

final class InspectorFixtureDto
{
    use AsDto;

    #[MapKey('display_name')]
    public ?string $displayName = null;

    /**
     * @param  array<int, InspectorChildFixtureDto>  $children
     */
    public function __construct(
        public readonly string $name,
        #[ArrayOf(InspectorChildFixtureDto::class)]
        public readonly array $children = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|min:3',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
        ];
    }
}

final class InspectorChildFixtureDto
{
    public function __construct(public readonly string $name) {}
}
