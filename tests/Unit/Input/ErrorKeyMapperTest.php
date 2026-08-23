<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Input\ErrorKeyMapper;
use Axiom\DataForge\Schema\DtoInspector;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Attributes\Objects\DtoWithMapKey;
use Tests\Unit\Attributes\Objects\DtoWithMapKeyNestedCollection;

class ErrorKeyMapperTest extends TestCase
{
    public function test_translates_mapped_flat_keys_to_input_keys(): void
    {
        $inspector = new DtoInspector(DtoWithMapKey::class);

        $mapped = ErrorKeyMapper::toInputKeys($inspector, [
            'firstName' => ['The first name field is required.'],
            'email' => ['The email field must be valid.'],
        ]);

        $this->assertArrayHasKey('first_name', $mapped);
        $this->assertArrayHasKey('email_address', $mapped);
        $this->assertSame(['The first name field is required.'], $mapped['first_name']);
    }

    public function test_leaves_unmapped_keys_untouched(): void
    {
        $inspector = new DtoInspector(DtoWithMapKey::class);

        $mapped = ErrorKeyMapper::toInputKeys($inspector, [
            'age' => ['too young'],
            'unknown' => ['not a property'],
        ]);

        $this->assertArrayHasKey('age', $mapped);
        $this->assertArrayHasKey('unknown', $mapped);
    }

    public function test_translates_nested_dto_path_segment_by_segment(): void
    {
        $inspector = new DtoInspector(MappedErrorKeyParentFixtureDto::class);

        $mapped = ErrorKeyMapper::toInputKeys($inspector, [
            'person.name' => ['The name field is too short.'],
        ]);

        // 'person' maps to 'person_data'; the nested 'name' has no MapKey.
        $this->assertSame(['person_data.name'], array_keys($mapped));
    }

    public function test_translates_collection_path_and_preserves_list_index(): void
    {
        $inspector = new DtoInspector(DtoWithMapKeyNestedCollection::class);

        $mapped = ErrorKeyMapper::toInputKeys($inspector, [
            'people.0.name' => ['The name field is required.'],
        ]);

        // 'people' maps to 'members'; the numeric list index passes through.
        $this->assertSame(['members.0.name'], array_keys($mapped));
    }

    public function test_returns_empty_bag_unchanged(): void
    {
        $inspector = new DtoInspector(DtoWithMapKey::class);

        $this->assertSame([], ErrorKeyMapper::toInputKeys($inspector, []));
    }
}

final class MappedErrorKeyParentFixtureDto
{
    use AsDto;

    public function __construct(
        #[MapKey('person_data')]
        public readonly MappedErrorKeyChildFixtureDto $person,
    ) {}
}

final class MappedErrorKeyChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}
