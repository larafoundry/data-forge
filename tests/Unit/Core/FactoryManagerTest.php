<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ws\DataBridge\Core\FactoryManager;

class FactoryManagerTest extends TestCase
{
    public function test_fill_random_generates_values(): void
    {
        $dto = FactoryManager::from(TestDto::class)
            ->fillRandom()
            ->with('email', 'test@example.com')
            ->make();

        $this->assertInstanceOf(TestDto::class, $dto);
        $this->assertIsString($dto->name);
        $this->assertIsInt($dto->age);
        $this->assertEquals('test@example.com', $dto->email);
        $this->assertIsBool($dto->active);
    }

    public function test_with_overrides_fill_random(): void
    {
        $dto = FactoryManager::from(TestDto::class)
            ->fillRandom()
            ->with('name', 'Test Name')
            ->with('email', 'test@example.com')
            ->make();

        $this->assertInstanceOf(TestDto::class, $dto);
        $this->assertEquals('Test Name', $dto->name);
        $this->assertEquals('test@example.com', $dto->email);
        $this->assertIsInt($dto->age); // Should still be randomly generated
    }

    public function test_with_values_overrides_fill_random(): void
    {
        $dto = FactoryManager::from(TestDto::class)
            ->fillRandom()
            ->withValues([
                'name' => 'Test Name',
                'age' => 30,
                'email' => 'test@example.com',
            ])
            ->make();

        $this->assertInstanceOf(TestDto::class, $dto);
        $this->assertEquals('Test Name', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertEquals('test@example.com', $dto->email);
    }

    public function test_with_throws_exception_for_non_existent_property(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FactoryManager::from(TestDto::class)
            ->with('nonExistentProperty', 'value')
            ->make();
    }

    public function test_with_values_throws_exception_for_non_existent_property(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FactoryManager::from(TestDto::class)
            ->withValues([
                'name' => 'Test Name',
                'nonExistentProperty' => 'value',
            ])
            ->make();
    }

    public function test_factory_method_from_dto(): void
    {
        $dto = TestDto::factory()
            ->fillRandom()
            ->with('name', 'Factory Method Test')
            ->with('email', 'test@example.com')
            ->make();

        $this->assertInstanceOf(TestDto::class, $dto);
        $this->assertEquals('Factory Method Test', $dto->name);
        $this->assertEquals('test@example.com', $dto->email);
    }
}
