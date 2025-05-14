<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ws\DataBridge\Core\FactoryBatch;

class FactoryBatchTest extends TestCase
{
    public function test_make_creates_requested_count(): void
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

    public function test_with_applied_to_all_objects(): void
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

    public function test_with_values_applied_to_all_objects(): void
    {
        $count = 3;
        $dtos = FactoryBatch::for(TestDto::class)
            ->count($count)
            ->fillRandom()
            ->withValues([
                'name' => 'Batch Test',
                'age' => 25,
            ])
            ->make();

        $this->assertCount($count, $dtos);

        foreach ($dtos as $dto) {
            $this->assertEquals('Batch Test', $dto->name);
            $this->assertEquals(25, $dto->age);
        }
    }

    public function test_count_throws_exception_for_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FactoryBatch::for(TestDto::class)
            ->count(0)
            ->make();
    }

    public function test_count_throws_exception_for_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FactoryBatch::for(TestDto::class)
            ->count(-1)
            ->make();
    }

    public function test_default_count_is_one(): void
    {
        $dtos = FactoryBatch::for(TestDto::class)
            ->fillRandom()
            ->with('email', 'test@example.com')
            ->make();

        $this->assertCount(1, $dtos);
    }

    public function test_each_object_has_unique_random_values(): void
    {
        $count = 10; // Higher count to increase chance of uniqueness
        $dtos = FactoryBatch::for(TestDto::class)
            ->count($count)
            ->fillRandom()
            ->with('email', 'test@example.com')
            ->make();

        $this->assertCount($count, $dtos);

        // Extract names to check for uniqueness
        $names = array_map(fn ($dto) => $dto->name, $dtos);

        // Check that at least some names are different (random generation should produce different values)
        $uniqueNames = array_unique($names);
        $this->assertGreaterThan(1, count($uniqueNames), 'Random generation should produce at least some unique values');
    }
}
