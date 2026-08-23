<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SpatieMappingPortTest extends TestCase
{
    public function test_flat_mapkey_is_the_accepted_input_key_for_required_field(): void
    {
        $dto = SpatieMapRequiredNameDto::fromArray([
            'display_name' => 'Ada Lovelace',
        ]);

        $this->assertSame('Ada Lovelace', $dto->displayName);
    }

    public function test_canonical_property_name_is_not_alias_for_mapped_required_field(): void
    {
        try {
            SpatieMapRequiredNameDto::fromArray([
                'displayName' => 'Ada Lovelace',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('display_name', $e->errors);
            $this->assertArrayNotHasKey('displayName', $e->errors);
        }
    }

    public function test_strict_mapped_field_rejects_canonical_property_name_as_unknown_input(): void
    {
        try {
            SpatieStrictMapRequiredNameDto::fromArray([
                'displayName' => 'Ada Lovelace',
            ]);

            $this->fail('Unknown input validation should have failed');
        } catch (UnknownInputKeyException $e) {
            $this->assertArrayHasKey('displayName', $e->errors);
            $this->assertArrayNotHasKey('display_name', $e->errors);
        }
    }

    public function test_dotted_mapkey_is_treated_as_literal_flat_key(): void
    {
        $dto = SpatieDottedMapKeyDto::fromArray([
            'nested.something' => 'literal key',
        ]);

        $this->assertSame('literal key', $dto->mapped);
    }

    public function test_dotted_mapkey_does_not_read_nested_payload_paths(): void
    {
        try {
            SpatieDottedMapKeyDto::fromArray([
                'nested' => [
                    'something' => 'not a Data Forge path mapping',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nested.something', $e->errors);
            $this->assertArrayNotHasKey('mapped', $e->errors);
        }
    }

    public function test_mapped_collection_hydrates_nested_items_using_child_external_keys(): void
    {
        $dto = SpatieMappedCollectionParentDto::fromArray([
            'people_data' => [
                [
                    'given_name' => 'Ada',
                    'email' => 'ada@example.com',
                ],
                [
                    'given_name' => 'Grace',
                    'email' => 'grace@example.com',
                ],
            ],
        ]);

        $this->assertInstanceOf(Collection::class, $dto->people);
        $this->assertSame(['Ada', 'Grace'], $dto->people->map->name->all());
    }

    public function test_mapped_collection_validation_errors_use_external_paths(): void
    {
        try {
            SpatieMappedCollectionParentDto::fromArray([
                'people_data' => [
                    [
                        'email' => 'ada@example.com',
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('people_data.0.given_name', $e->errors);
            $this->assertArrayNotHasKey('people.0.name', $e->errors);
        }
    }
}

final class SpatieMapRequiredNameDto
{
    use AsDto;

    public function __construct(
        #[MapKey('display_name')]
        public readonly string $displayName,
    ) {}
}

final class SpatieStrictMapRequiredNameDto
{
    use AsStrictInputDto;

    public function __construct(
        #[MapKey('display_name')]
        public readonly string $displayName,
    ) {}
}

final class SpatieDottedMapKeyDto
{
    use AsDto;

    public function __construct(
        #[MapKey('nested.something')]
        public readonly string $mapped,
    ) {}
}

final class SpatieMappedCollectionParentDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieMappedCollectionProfileDto>  $people
     */
    public function __construct(
        #[MapKey('people_data')]
        #[ArrayOf(SpatieMappedCollectionProfileDto::class)]
        public readonly Collection $people,
    ) {}
}

final class SpatieMappedCollectionProfileDto
{
    use AsDto;

    public function __construct(
        #[MapKey('given_name')]
        public readonly string $name,
        public readonly string $email,
    ) {}
}
