<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Tests\Feature\AsDto\Objects\BasicDto;
use Tests\Feature\AsDto\Objects\InvalidArrayOfArrayDto;
use Tests\Feature\AsDto\Objects\MappedNestedDtoWithDottedRules;
use Tests\Feature\AsDto\Objects\NestedCollectionDto;
use Tests\Feature\AsDto\Objects\NestedCollectionDtoWithArrayRule;
use Tests\Feature\AsDto\Objects\NestedDto;
use Tests\Feature\AsDto\Objects\NestedDtoWithArrayRule;
use Tests\Feature\AsDto\Objects\NestedDtoWithDottedRules;
use Tests\Feature\AsDto\Objects\NullableNestedCollectionDto;
use Tests\Feature\AsDto\Objects\NullableNestedDto;
use Tests\Feature\AsDto\Objects\NullableNestedNoDefaultDto;

class NestedDtoTest extends TestCase
{
    public function test_can_create_nested_dto(): void
    {
        // First, create a BasicDto instance
        $personData = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
        ];

        /** @noinspection PhpUnhandledExceptionInspection */
        $person = BasicDto::fromArray($personData);

        // Now create a NestedDto using the BasicDto
        $data = [
            'title' => 'Manager',
            'person' => $person,
        ];

        $nestedDto = new NestedDto(
            title: $data['title'],
            person: $data['person']
        );

        $this->assertEquals('Manager', $nestedDto->title);
        $this->assertInstanceOf(BasicDto::class, $nestedDto->person);
        $this->assertEquals('John Doe', $nestedDto->person->name);
        $this->assertEquals(30, $nestedDto->person->age);
        $this->assertEquals('john@example.com', $nestedDto->person->email);
    }

    public function test_can_create_nested_dto_from_array(): void
    {
        $nestedDto = NestedDto::fromArray([
            'title' => 'Manager',
            'person' => [
                'name' => 'John Doe',
                'age' => 30,
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertEquals('Manager', $nestedDto->title);
        $this->assertInstanceOf(BasicDto::class, $nestedDto->person);
        $this->assertEquals('John Doe', $nestedDto->person->name);
        $this->assertEquals(30, $nestedDto->person->age);
        $this->assertEquals('john@example.com', $nestedDto->person->email);
    }

    public function test_nested_dto_hydration_does_not_call_custom_from_array_override(): void
    {
        NestedFromArrayOverrideChildDto::$fromArrayCalls = 0;

        $nestedDto = NestedFromArrayOverrideParentDto::fromArray([
            'child' => [
                'name' => 'Jane',
            ],
        ]);

        $this->assertSame(0, NestedFromArrayOverrideChildDto::$fromArrayCalls);
        $this->assertSame('Jane', $nestedDto->child->name);
    }

    public function test_can_create_nullable_nested_dto_with_explicit_null(): void
    {
        $nestedDto = NullableNestedDto::fromArray([
            'title' => 'Manager',
            'person' => null,
        ]);

        $this->assertEquals('Manager', $nestedDto->title);
        $this->assertNull($nestedDto->person);
    }

    public function test_missing_nullable_nested_dto_without_default_becomes_null(): void
    {
        $nestedDto = NullableNestedNoDefaultDto::fromArray([
            'title' => 'Manager',
        ]);

        $this->assertEquals('Manager', $nestedDto->title);
        $this->assertNull($nestedDto->person);
    }

    public function test_array_rule_validates_raw_nested_dto_input_before_hydration(): void
    {
        $nestedDto = NestedDtoWithArrayRule::fromArray([
            'title' => 'Manager',
            'person' => [
                'name' => 'John Doe',
                'age' => 30,
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertInstanceOf(BasicDto::class, $nestedDto->person);
    }

    public function test_can_create_collection_of_nested_dtos_from_arrays(): void
    {
        $nestedDto = NestedCollectionDto::fromArray([
            'title' => 'Team',
            'people' => [
                [
                    'name' => 'John Doe',
                    'age' => 30,
                    'email' => 'john@example.com',
                ],
                [
                    'name' => 'Jane Doe',
                    'age' => 28,
                    'email' => 'jane@example.com',
                ],
            ],
        ]);

        $this->assertEquals('Team', $nestedDto->title);
        $this->assertInstanceOf(Collection::class, $nestedDto->people);
        $this->assertContainsOnlyInstancesOf(BasicDto::class, $nestedDto->people->all());
        $this->assertEquals('John Doe', $nestedDto->people[0]->name);
        $this->assertEquals('John Doe', $nestedDto->people->first()->name);
        $this->assertEquals('Jane Doe', $nestedDto->people[1]->name);
    }

    public function test_array_rule_validates_raw_nested_collection_input_before_hydration(): void
    {
        $nestedDto = NestedCollectionDtoWithArrayRule::fromArray([
            'title' => 'Team',
            'people' => [
                [
                    'name' => 'John Doe',
                    'age' => 30,
                    'email' => 'john@example.com',
                ],
            ],
        ]);

        $this->assertInstanceOf(Collection::class, $nestedDto->people);
        $this->assertContainsOnlyInstancesOf(BasicDto::class, $nestedDto->people->all());
    }

    public function test_can_create_nullable_collection_of_nested_dtos_with_explicit_null(): void
    {
        $nestedDto = NullableNestedCollectionDto::fromArray([
            'title' => 'Team',
            'people' => null,
        ]);

        $this->assertEquals('Team', $nestedDto->title);
        $this->assertNull($nestedDto->people);
    }

    public function test_collection_of_nested_dtos_is_iterable(): void
    {
        $nestedDto = NestedCollectionDto::fromArray([
            'title' => 'Team',
            'people' => [
                [
                    'name' => 'John Doe',
                    'age' => 30,
                    'email' => 'john@example.com',
                ],
                [
                    'name' => 'Jane Doe',
                    'age' => 28,
                    'email' => 'jane@example.com',
                ],
            ],
        ]);

        $names = [];
        foreach ($nestedDto->people as $person) {
            $this->assertInstanceOf(BasicDto::class, $person);
            $names[] = $person->name;
        }

        $this->assertSame(['John Doe', 'Jane Doe'], $names);
    }

    public function test_nested_collection_rejects_associative_array_input(): void
    {
        try {
            NestedCollectionDto::fromArray([
                'title' => 'Team',
                'people' => [
                    'lead' => [
                        'name' => 'John Doe',
                        'age' => 30,
                        'email' => 'john@example.com',
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('people', $e->errors);
        }
    }

    public function test_nested_validation_errors_use_dot_paths(): void
    {
        try {
            NestedDto::fromArray([
                'title' => 'Manager',
                'person' => [
                    'name' => 'John Doe',
                    'age' => 'invalid',
                    'email' => 'john@example.com',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('person.age', $e->errors);
        }
    }

    public function test_custom_dotted_rules_validate_nested_dto_input(): void
    {
        try {
            NestedDtoWithDottedRules::fromArray([
                'title' => 'Manager',
                'person' => [
                    'name' => 'Jo',
                    'age' => 30,
                    'email' => 'john@example.com',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('person.name', $e->errors);
        }
    }

    public function test_custom_dotted_rules_map_nested_dto_root_keys(): void
    {
        try {
            MappedNestedDtoWithDottedRules::fromArray([
                'title' => 'Manager',
                'person_data' => [
                    'name' => 'Jo',
                    'age' => 30,
                    'email' => 'john@example.com',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('person_data.name', $e->errors);
        }
    }

    public function test_array_nested_validation_errors_use_dot_paths(): void
    {
        try {
            NestedCollectionDto::fromArray([
                'title' => 'Team',
                'people' => [
                    [
                        'name' => 'John Doe',
                        'age' => 'invalid',
                        'email' => 'john@example.com',
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('people.0.age', $e->errors);
        }
    }

    public function test_collection_input_is_rejected_for_nested_collection_fields(): void
    {
        try {
            NestedCollectionDto::fromArray([
                'title' => 'Team',
                'people' => new Collection([
                    [
                        'name' => 'John Doe',
                        'age' => 30,
                        'email' => 'john@example.com',
                    ],
                ]),
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('people', $e->errors);
        }
    }

    public function test_array_typed_array_of_fields_are_rejected(): void
    {
        try {
            InvalidArrayOfArrayDto::fromArray([
                'people' => [],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('people', $e->errors);
        }
    }
}

final class NestedFromArrayOverrideParentDto
{
    use AsDto;

    public function __construct(public readonly NestedFromArrayOverrideChildDto $child) {}
}

final class NestedFromArrayOverrideChildDto
{
    use AsDto;

    public static int $fromArrayCalls = 0;

    public function __construct(public readonly string $name) {}

    /**
     * @param  array<string,mixed>  $attributes
     */
    public static function fromArray(array $attributes): static
    {
        self::$fromArrayCalls++;

        return new self(mb_strtoupper((string) $attributes['name']));
    }
}
