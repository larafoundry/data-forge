<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\ComplexObject;
use Tests\Unit\AsDto\Objects\EnumType;

class AutoCastTest extends TestCase
{
    public function test_enum_casting_from_string(): void
    {
        $data = [
            'enumType' => 'required',
            'carbon' => new Carbon(),
            'carbonImmutable' => new CarbonImmutable(),
            'dateTimeImmutable' => new DateTimeImmutable(),
            'dateTime' => new DateTime(),
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(EnumType::class, $dto->enumType);
        $this->assertEquals(EnumType::REQUIRED, $dto->enumType);
    }

    public function test_carbon_casting_from_string(): void
    {
        $data = [
            'enumType' => EnumType::REQUIRED,
            'carbon' => '2023-05-15 10:30:00',
            'carbonImmutable' => new CarbonImmutable(),
            'dateTimeImmutable' => new DateTimeImmutable(),
            'dateTime' => new DateTime(),
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(Carbon::class, $dto->carbon);
        $this->assertEquals('2023-05-15 10:30:00', $dto->carbon->format('Y-m-d H:i:s'));
    }

    public function test_carbon_immutable_casting_from_string(): void
    {
        $data = [
            'enumType' => EnumType::REQUIRED,
            'carbon' => new Carbon(),
            'carbonImmutable' => '2023-06-20 15:45:00',
            'dateTimeImmutable' => new DateTimeImmutable(),
            'dateTime' => new DateTime(),
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(CarbonImmutable::class, $dto->carbonImmutable);
        $this->assertEquals('2023-06-20 15:45:00', $dto->carbonImmutable->format('Y-m-d H:i:s'));
    }

    public function test_date_time_immutable_casting_from_string(): void
    {
        $data = [
            'enumType' => EnumType::REQUIRED,
            'carbon' => new Carbon(),
            'carbonImmutable' => new CarbonImmutable(),
            'dateTimeImmutable' => '2023-07-25 08:15:00',
            'dateTime' => new DateTime(),
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(DateTimeImmutable::class, $dto->dateTimeImmutable);
        $this->assertEquals('2023-07-25 08:15:00', $dto->dateTimeImmutable->format('Y-m-d H:i:s'));
    }

    public function test_date_time_casting_from_string(): void
    {
        $data = [
            'enumType' => EnumType::REQUIRED,
            'carbon' => new Carbon(),
            'carbonImmutable' => new CarbonImmutable(),
            'dateTimeImmutable' => new DateTimeImmutable(),
            'dateTime' => '2023-08-30 20:00:00',
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(DateTime::class, $dto->dateTime);
        $this->assertEquals('2023-08-30 20:00:00', $dto->dateTime->format('Y-m-d H:i:s'));
    }

    public function test_all_types_casting_from_string(): void
    {
        $data = [
            'enumType' => 'optional',
            'carbon' => '2023-01-01 12:00:00',
            'carbonImmutable' => '2023-02-02 13:00:00',
            'dateTimeImmutable' => '2023-03-03 14:00:00',
            'dateTime' => '2023-04-04 15:00:00',
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(EnumType::class, $dto->enumType);
        $this->assertEquals(EnumType::OPTIONAL, $dto->enumType);

        $this->assertInstanceOf(Carbon::class, $dto->carbon);
        $this->assertEquals('2023-01-01 12:00:00', $dto->carbon->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(CarbonImmutable::class, $dto->carbonImmutable);
        $this->assertEquals('2023-02-02 13:00:00', $dto->carbonImmutable->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(DateTimeImmutable::class, $dto->dateTimeImmutable);
        $this->assertEquals('2023-03-03 14:00:00', $dto->dateTimeImmutable->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(DateTime::class, $dto->dateTime);
        $this->assertEquals('2023-04-04 15:00:00', $dto->dateTime->format('Y-m-d H:i:s'));
    }
}
