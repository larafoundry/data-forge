<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes;

use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Hydration\ContainerHelper;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Validation\Validator;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Attributes\Objects\DtoWithMapKey;
use Tests\Unit\Attributes\Objects\StrictDtoWithMapKeyNestedCollection;

class MapKeyTest extends TestCase
{
    public function test_can_create_dto_with_mapped_keys(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john.doe@example.com',
            'age' => 30,
        ];

        $dto = DtoWithMapKey::fromArray($data);

        $this->assertInstanceOf(DtoWithMapKey::class, $dto);
        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john.doe@example.com', $dto->email);
        $this->assertEquals(30, $dto->age);
    }

    public function test_container_helper_uses_canonical_property_keys_only(): void
    {
        $data = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'age' => 30,
        ];

        $dto = ContainerHelper::makeInstance(DtoWithMapKey::class, $data);

        $this->assertInstanceOf(DtoWithMapKey::class, $dto);
        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john.doe@example.com', $dto->email);
        $this->assertEquals(30, $dto->age);
    }

    public function test_container_helper_does_not_process_mapped_input_keys(): void
    {
        $this->expectException(InstantiationException::class);

        ContainerHelper::makeInstance(DtoWithMapKey::class, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john.doe@example.com',
            'age' => 30,
        ]);
    }

    public function test_can_validate_dto_with_mapped_keys(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john.doe@example.com',
            'age' => 30,
        ];

        $inspector = new DtoInspector(DtoWithMapKey::class);
        $validator = Validator::from($inspector, $data)
            ->withRules([
                'firstName' => 'required|min:3',
                'lastName' => 'required|min:2',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ]);

        $validated = $validator->validate();

        $this->assertEquals('John', $validated['firstName']);
        $this->assertEquals('Doe', $validated['lastName']);
        $this->assertEquals('john.doe@example.com', $validated['email']);
        $this->assertEquals(30, $validated['age']);
    }

    public function test_validation_fails_with_mapped_keys(): void
    {
        $data = [
            'first_name' => 'Jo', // Too short
            'last_name' => 'D', // Too short
            'email_address' => 'not-an-email',
            'age' => 17, // Too young
        ];

        $inspector = new DtoInspector(DtoWithMapKey::class);
        $validator = Validator::from($inspector, $data)
            ->withRules([
                'firstName' => 'required|min:3',
                'lastName' => 'required|min:2',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ]);

        try {
            $validator->validate();
            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $errors = $e->errors;

            // Errors are reported using the external input keys the caller sent
            $this->assertArrayHasKey('first_name', $errors);
            $this->assertArrayHasKey('last_name', $errors);
            $this->assertArrayHasKey('email_address', $errors);
            $this->assertArrayHasKey('age', $errors);
        }
    }

    public function test_get_errors_uses_input_keys_after_validate_safe(): void
    {
        $data = [
            'first_name' => 'Jo', // Too short
            'last_name' => 'D', // Too short
            'email_address' => 'not-an-email',
            'age' => 17, // Too young
        ];

        $inspector = new DtoInspector(DtoWithMapKey::class);
        $validator = Validator::from($inspector, $data)
            ->withRules([
                'firstName' => 'required|min:3',
                'lastName' => 'required|min:2',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ]);

        $this->assertFalse($validator->validateSafe());

        // getErrors() must agree with the namespace a caught exception would use:
        // the external input keys, not the canonical property names.
        $errors = $validator->getErrors();
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertArrayHasKey('email_address', $errors);
        $this->assertArrayNotHasKey('firstName', $errors);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function test_non_strict_validation_ignores_original_key_when_mapped_key_is_provided(): void
    {
        $data = [
            'first_name' => 'John',
            'firstName' => 'Jane',
            'last_name' => 'Doe',
            'email_address' => 'john.doe@example.com',
            'age' => 30,
        ];

        $inspector = new DtoInspector(DtoWithMapKey::class);
        $validator = Validator::from($inspector, $data)
            ->withRules([
                'firstName' => 'required|min:3',
                'lastName' => 'required|min:2',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ]);

        $validated = $validator->validate();

        $this->assertSame('John', $validated['firstName']);
    }

    public function test_strict_validation_fails_when_original_key_is_provided_for_mapped_property(): void
    {
        $data = [
            'first_name' => 'John',
            'firstName' => 'Jane',
            'last_name' => 'Doe',
            'email_address' => 'john.doe@example.com',
            'age' => 30,
        ];

        $inspector = new DtoInspector(DtoWithMapKey::class);
        $validator = Validator::from($inspector, $data, rejectsUnknownInputKeys: true)
            ->withRules([
                'firstName' => 'required|min:3',
                'lastName' => 'required|min:2',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ]);

        try {
            $validator->validate();
            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('firstName', $e->errors);
        }
    }

    public function test_strict_mapkey_original_key_is_detected_before_nested_collection_hydration(): void
    {
        try {
            StrictDtoWithMapKeyNestedCollection::fromArray([
                'people' => 'not-an-array',
                'members' => [
                    ['name' => 'Alice'],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('people', $e->errors);
        }
    }

    public function test_inspector_returns_correct_key_map(): void
    {
        $inspector = new DtoInspector(DtoWithMapKey::class);
        $keyMap = $inspector->getKeyMap();

        $this->assertEquals([
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'email' => 'email_address',
            'age' => 'age',
        ], $keyMap);
    }
}
