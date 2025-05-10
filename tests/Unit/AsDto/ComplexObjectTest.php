<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\ComplexObject;
use Tests\Unit\AsDto\Objects\EnumType;

class ComplexObjectTest extends TestCase
{
    public function test_complex_object_can_be_instantiated(): void
    {
        $now = new DateTime();
        $nowImmutable = new DateTimeImmutable();
        $carbon = Carbon::now();
        $carbonImmutable = CarbonImmutable::now();
        $enumType = EnumType::REQUIRED;

        $complexObject = new ComplexObject(
            enumType: $enumType,
            carbon: $carbon,
            carbonImmutable: $carbonImmutable,
            dateTimeImmutable: $nowImmutable,
            dateTime: $now
        );

        $this->assertSame($enumType, $complexObject->enumType);
        $this->assertSame($carbon, $complexObject->carbon);
        $this->assertSame($carbonImmutable, $complexObject->carbonImmutable);
        $this->assertSame($nowImmutable, $complexObject->dateTimeImmutable);
        $this->assertSame($now, $complexObject->dateTime);
    }

    public function test_complex_object_properties_are_immutable(): void
    {
        $now = new DateTime();
        $nowImmutable = new DateTimeImmutable();
        $carbon = Carbon::now();
        $carbonImmutable = CarbonImmutable::now();
        $enumType = EnumType::REQUIRED;

        /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
        $complexObject = new ComplexObject(
            enumType: $enumType,
            carbon: $carbon,
            carbonImmutable: $carbonImmutable,
            dateTimeImmutable: $nowImmutable,
            dateTime: $now
        );

        $this->expectException(Error::class);
        /** @noinspection PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection */
        $complexObject->enumType = EnumType::OPTIONAL;
    }

    public function test_can_create_from_array_with_strings(): void
    {
        $data = [
            'enumType' => 'required',
            'carbon' => '2023-01-01 12:00:00',
            'carbonImmutable' => '2023-01-02 12:00:00',
            'dateTimeImmutable' => '2023-01-03 12:00:00',
            'dateTime' => '2023-01-04 12:00:00',
        ];

        $dto = ComplexObject::fromArray($data);

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

    public function test_can_create_from_array_with_instances(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $carbonImmutable = CarbonImmutable::parse('2023-01-02 12:00:00');
        $dateTimeImmutable = new DateTimeImmutable('2023-01-03 12:00:00');
        $dateTime = new DateTime('2023-01-04 12:00:00');

        $data = [
            'enumType' => EnumType::OPTIONAL,
            'carbon' => $carbon,
            'carbonImmutable' => $carbonImmutable,
            'dateTimeImmutable' => $dateTimeImmutable,
            'dateTime' => $dateTime,
        ];

        $dto = ComplexObject::fromArray($data);

        $this->assertInstanceOf(EnumType::class, $dto->enumType);
        $this->assertEquals(EnumType::OPTIONAL, $dto->enumType);

        $this->assertSame($carbon, $dto->carbon);
        $this->assertSame($carbonImmutable, $dto->carbonImmutable);
        $this->assertSame($dateTimeImmutable, $dto->dateTimeImmutable);
        $this->assertSame($dateTime, $dto->dateTime);
    }
}
