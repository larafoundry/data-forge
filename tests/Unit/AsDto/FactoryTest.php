<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\BasicDto;
use Tests\Unit\AsDto\Objects\ComplexObject;
use Tests\Unit\AsDto\Objects\EnumType;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;

class FactoryTest extends TestCase
{
    public function test_can_create_dto_using_factory(): void
    {
        $dto = BasicDto::factory()
            ->with('name', 'John Doe')
            ->with('age', 30)
            ->with('email', 'john@example.com')
            ->make();

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_can_create_dto_with_nullable_property_using_factory(): void
    {
        $dto = BasicDto::factory()
            ->with('name', 'John Doe')
            ->with('age', 30)
            ->make();

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertNull($dto->email);
    }

    public function test_can_create_dto_using_factory_with_values(): void
    {
        $dto = BasicDto::factory()
            ->withValues([
                'name' => 'John Doe',
                'age' => 30,
                'email' => 'john@example.com',
            ])
            ->make();

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_can_create_complex_object_using_factory(): void
    {
        $now = new DateTime();
        $nowImmutable = new DateTimeImmutable();
        $carbon = Carbon::now();
        $carbonImmutable = CarbonImmutable::now();
        $enumType = EnumType::REQUIRED;

        $dto = ComplexObject::factory()
            ->withValues([
                'enumType' => $enumType,
                'carbon' => $carbon,
                'carbonImmutable' => $carbonImmutable,
                'dateTimeImmutable' => $nowImmutable,
                'dateTime' => $now,
            ])
            ->make();

        $this->assertInstanceOf(ComplexObject::class, $dto);
        $this->assertSame($enumType, $dto->enumType);
        $this->assertSame($carbon, $dto->carbon);
        $this->assertSame($carbonImmutable, $dto->carbonImmutable);
        $this->assertSame($nowImmutable, $dto->dateTimeImmutable);
        $this->assertSame($now, $dto->dateTime);
    }

    public function test_can_create_complex_object_with_string_values_using_factory(): void
    {
        $dto = ComplexObject::factory()
            ->withValues([
                'enumType' => 'required',
                'carbon' => '2023-01-01 12:00:00',
                'carbonImmutable' => '2023-01-02 12:00:00',
                'dateTimeImmutable' => '2023-01-03 12:00:00',
                'dateTime' => '2023-01-04 12:00:00',
            ])
            ->make();

        $this->assertInstanceOf(ComplexObject::class, $dto);
        $this->assertInstanceOf(EnumType::class, $dto->enumType);
        $this->assertEquals(EnumType::REQUIRED, $dto->enumType);

        // Verify Carbon auto-casting
        $this->assertInstanceOf(Carbon::class, $dto->carbon);
        $this->assertEquals('2023-01-01 12:00:00', $dto->carbon->format('Y-m-d H:i:s'));

        // Verify CarbonImmutable auto-casting
        $this->assertInstanceOf(CarbonImmutable::class, $dto->carbonImmutable);
        $this->assertEquals('2023-01-02 12:00:00', $dto->carbonImmutable->format('Y-m-d H:i:s'));

        // Verify DateTimeImmutable auto-casting
        $this->assertInstanceOf(DateTimeImmutable::class, $dto->dateTimeImmutable);
        $this->assertEquals('2023-01-03 12:00:00', $dto->dateTimeImmutable->format('Y-m-d H:i:s'));

        // Verify DateTime auto-casting
        $this->assertInstanceOf(DateTime::class, $dto->dateTime);
        $this->assertEquals('2023-01-04 12:00:00', $dto->dateTime->format('Y-m-d H:i:s'));
    }
}