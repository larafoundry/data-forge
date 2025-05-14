<?php

namespace Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ws\DataBridge\Core\FactoryBatch;

class FactoryBatchTest extends TestCase
{
    public function testMakeCreatesRequestedCount(): void
    {
        $count = 5;
        $dtos = FactoryBatch::for(TestDto::class)
            ->count($count)
            ->fillRandom()
            ->with('email', 'test@example.com')
            ->make();

        $this->assertCount($count, $dtos);

        foreach ($dtos as $dto) {
            $this->assertInstanceOf(TestDto::class, $dto);
            $this->assertEquals('test@example.com', $dto->email);
        }
    }

    public function testWithAppliedToAllObjects(): void
    {
        $count = 3;
        $dtos = FactoryBatch::for(TestDto::class)
            ->count($count)
            ->fillRandom()
            ->with('name', 'Batch Test')
            ->with('email', 'test@example.com')
            ->make();

        $this->assertCount($count, $dtos);

        foreach ($dtos as $dto) {
            $this->assertEquals('Batch Test', $dto->name);
            $this->assertEquals('test@example.com', $dto->email);
        }
    }

    public function testWithValuesAppliedToAllObjects(): void
    {
        $count = 3;
        $dtos = FactoryBatch::for(TestDto::class)
            ->count($count)
            ->fillRandom()
            ->withValues([
                'name' => 'Batch Test',
                'age' => 25
            ])
            ->make();

        $this->assertCount($count, $dtos);

        foreach ($dtos as $dto) {
            $this->assertEquals('Batch Test', $dto->name);
            $this->assertEquals(25, $dto->age);
        }
    }

    public function testCountThrowsExceptionForZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FactoryBatch::for(TestDto::class)
            ->count(0)
            ->make();
    }

    public function testCountThrowsExceptionForNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FactoryBatch::for(TestDto::class)
            ->count(-1)
            ->make();
    }

    public function testDefaultCountIsOne(): void
    {
        $dtos = FactoryBatch::for(TestDto::class)
            ->fillRandom()
            ->with('email', 'test@example.com')
            ->make();

        $this->assertCount(1, $dtos);
    }

    public function testEachObjectHasUniqueRandomValues(): void
    {
        $count = 10; // Higher count to increase chance of uniqueness
        $dtos = FactoryBatch::for(TestDto::class)
            ->count($count)
            ->fillRandom()
            ->with('email', 'test@example.com')
            ->make();

        $this->assertCount($count, $dtos);

        // Extract names to check for uniqueness
        $names = array_map(fn($dto) => $dto->name, $dtos);

        // Check that at least some names are different (random generation should produce different values)
        $uniqueNames = array_unique($names);
        $this->assertGreaterThan(1, count($uniqueNames), 'Random generation should produce at least some unique values');
    }
}
