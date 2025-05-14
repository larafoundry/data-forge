<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Attributes\Objects\DtoWithMapKey;
use Ws\DataBridge\Core\ContainerHelper;
use Ws\DataBridge\Core\DtoInspector;
use Ws\DataBridge\Core\Validator;
use Ws\DataBridge\Exceptions\ValidationException;

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

        $dto = ContainerHelper::makeInstance(DtoWithMapKey::class, $data);

        $this->assertInstanceOf(DtoWithMapKey::class, $dto);
        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john.doe@example.com', $dto->email);
        $this->assertEquals(30, $dto->age);
    }

    public function test_can_create_dto_with_original_keys(): void
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


            // Check that we have errors for all properties
            $this->assertArrayHasKey('firstName', $errors);
            $this->assertArrayHasKey('lastName', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('age', $errors);
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
