<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\BasicDto;

class BasicDtoTest extends TestCase
{
    public function test_can_create_dto_from_array(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
        ];

        $dto = BasicDto::fromArray($data);

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_can_create_dto_with_nullable_property(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
        ];

        $dto = BasicDto::fromArray($data);

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertNull($dto->email);
    }

    public function test_default_rules_method_returns_empty_array(): void
    {
        $this->assertEmpty(BasicDto::rules());
    }

    public function test_default_messages_method_returns_empty_array(): void
    {
        $this->assertEmpty(BasicDto::messages());
    }
}
