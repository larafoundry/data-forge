<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Tests\Unit\AsDto\Objects\DtoWithRules;
use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\EnumType;
use Ws\DataBridge\Exceptions\ValidationException;

class DtoWithRulesTest extends TestCase
{
    public function testCanCreateDtoWithValidData(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'john@example.com',
            'tags' => ['tag1', 'tag2'],
            'enumType' => EnumType::REQUIRED
        ];

        $dto = DtoWithRules::fromArray($data);

        $this->assertInstanceOf(DtoWithRules::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(25, $dto->age);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function testThrowsExceptionWhenNameTooShort(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'Jo',  // Less than 3 characters
            'age' => 25,
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function testThrowsExceptionWhenAgeTooYoung(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'age' => 17,  // Less than 18
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function testThrowsExceptionWhenEmailInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'not-an-email',  // Invalid email
        ];

        DtoWithRules::fromArray($data);
    }

    public function testRulesMethodReturnsExpectedRules(): void
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
