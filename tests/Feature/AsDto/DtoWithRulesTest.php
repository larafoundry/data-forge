<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature\AsDto;

use Axiom\DataForge\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Tests\Feature\AsDto\Objects\DtoWithRules;

class DtoWithRulesTest extends TestCase
{
    public function test_can_create_dto_with_valid_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'john@example.com',
            'tags' => ['tag1', 'tag2'],
            'enumType' => 'required',
        ];

        $dto = DtoWithRules::fromArray($data);

        $this->assertInstanceOf(DtoWithRules::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(25, $dto->age);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_throws_exception_when_name_too_short(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'Jo',  // Less than 3 characters
            'age' => 25,
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_throws_exception_when_age_too_young(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'age' => 17,  // Less than 18
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_throws_exception_when_email_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'not-an-email',  // Invalid email
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_rules_method_returns_expected_rules(): void
    {
        $rules = DtoWithRules::rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('age', $rules);
        $this->assertArrayHasKey('email', $rules);

        $this->assertEquals('required|string|min:3', $rules['name']);
        $this->assertEquals('required|integer|min:18', $rules['age']);
        $this->assertEquals('required|email', $rules['email']);
    }
}
