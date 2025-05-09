<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Tests\Unit\AsDto\Objects\BasicDto;
use PHPUnit\Framework\TestCase;

class BasicDtoTest extends TestCase
{
    public function testCanCreateDtoFromArray(): void
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

    public function testCanCreateDtoWithNullableProperty(): void
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

    public function testDefaultRulesMethodReturnsEmptyArray(): void
    {
        $this->assertEmpty(BasicDto::rules());
    }

    public function testDefaultMessagesMethodReturnsEmptyArray(): void
    {
        $this->assertEmpty(BasicDto::messages());
    }
}
