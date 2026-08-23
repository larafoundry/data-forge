<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\ValidationException;
use Carbon\Carbon;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

final class CoreValidationTest extends TestCase
{
    public function test_mixed_typed_field_accepts_array_payload(): void
    {
        $payload = [
            'nested' => [
                'ok' => true,
            ],
        ];

        $dto = MixedPayloadFixtureDto::fromArray([
            'payload' => $payload,
        ]);

        $this->assertSame($payload, $dto->payload);
    }

    public function test_float_typed_field_accepts_integer_numeric_input(): void
    {
        $dto = FloatAmountFixtureDto::fromArray([
            'amount' => 1,
        ]);

        $this->assertSame(1.0, $dto->amount);
        $this->assertIsFloat($dto->amount);
    }

    public function test_float_typed_field_rejects_numeric_strings(): void
    {
        $this->expectException(ValidationException::class);

        FloatAmountFixtureDto::fromArray([
            'amount' => '1.5',
        ]);
    }

    public function test_float_typed_field_rejects_bool_input(): void
    {
        $this->expectException(ValidationException::class);

        FloatAmountFixtureDto::fromArray([
            'amount' => true,
        ]);
    }

    public function test_int_typed_field_rejects_lossy_float_input(): void
    {
        $this->expectException(ValidationException::class);

        IntegerAmountFixtureDto::fromArray([
            'amount' => 1.5,
        ]);
    }

    public function test_iterable_typed_field_is_not_supported_by_as_dto(): void
    {
        $this->expectException(ValidationException::class);

        IterablePayloadFixtureDto::fromArray([
            'items' => ['first', 'second'],
        ]);
    }

    public function test_empty_string_for_optional_datetime_becomes_null_instead_of_current_datetime(): void
    {
        $dto = OptionalEmptyDateStringFixtureDto::fromArray([
            'createdAt' => '',
        ]);

        $this->assertNull($dto->createdAt);
    }

    public function test_date_format_attribute_drives_carbon_casting_for_non_iso_format(): void
    {
        $dto = DayFirstDateFormatFixtureDto::fromArray([
            'date' => '31/12/2024',
        ]);

        $this->assertSame('2024-12-31', $dto->date->format('Y-m-d'));
    }

    public function test_date_format_attribute_rejects_already_typed_carbon_instance_like_laravel_validation(): void
    {
        $this->expectException(ValidationException::class);

        $date = Carbon::create(2024, 12, 31, 0, 0, 0, 'UTC');

        DateFormatCarbonInstanceFixtureDto::fromArray([
            'date' => $date,
        ]);
    }

    public function test_date_format_attribute_rejects_string_that_does_not_match_format(): void
    {
        $this->expectException(ValidationException::class);

        DayFirstDateFormatFixtureDto::fromArray([
            'date' => '2024-12-31',
        ]);
    }

    public function test_invalid_date_format_timezone_is_configuration_failure(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Invalid timezone');

        InvalidTimezoneDateFormatFixtureDto::fromArray([
            'date' => '2024-01-02',
        ]);
    }

    public function test_invalid_date_format_timezone_is_configuration_failure_when_optional_field_is_missing(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Invalid timezone');

        OptionalInvalidTimezoneDateFormatFixtureDto::fromArray([]);
    }

    public function test_invalid_date_format_timezone_is_configuration_failure_before_typed_date_input_is_validated(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Invalid timezone');

        InvalidTimezoneDateFormatFixtureDto::fromArray([
            'date' => Carbon::create(2024, 1, 2, 0, 0, 0, 'UTC'),
        ]);
    }

    public function test_date_time_interface_accepts_parseable_date_string(): void
    {
        $dto = DateTimeInterfaceStringFixtureDto::fromArray([
            'date' => '2024-01-02 03:04:05',
        ]);

        $this->assertInstanceOf(DateTimeInterface::class, $dto->date);
        $this->assertSame('2024-01-02 03:04:05', $dto->date->format('Y-m-d H:i:s'));
    }
}

final class MixedPayloadFixtureDto
{
    use AsDto;

    public function __construct(public readonly mixed $payload) {}
}

final class FloatAmountFixtureDto
{
    use AsDto;

    public function __construct(public readonly float $amount) {}
}

final class IntegerAmountFixtureDto
{
    use AsDto;

    public function __construct(public readonly int $amount) {}
}

final class IterablePayloadFixtureDto
{
    use AsDto;

    /**
     * @param  iterable<int, string>  $items
     */
    public function __construct(public readonly iterable $items) {}
}

final class OptionalEmptyDateStringFixtureDto
{
    use AsDto;

    public function __construct(public readonly ?Carbon $createdAt = null) {}
}

final class DayFirstDateFormatFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('d/m/Y')]
        public readonly Carbon $date,
    ) {}
}

final class DateFormatCarbonInstanceFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d')]
        public readonly Carbon $date,
    ) {}
}

final class InvalidTimezoneDateFormatFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d', timezone: 'Not/AZone')]
        public readonly Carbon $date,
    ) {}
}

final class OptionalInvalidTimezoneDateFormatFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d', timezone: 'Not/AZone')]
        public readonly ?Carbon $date = null,
    ) {}
}

final class DateTimeInterfaceStringFixtureDto
{
    use AsDto;

    public function __construct(public readonly DateTimeInterface $date) {}
}
