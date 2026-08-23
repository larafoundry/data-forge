<?php

declare(strict_types=1);

namespace Tests\Unit\Hydration;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Hydration\DtoHydrator;
use Axiom\DataForge\Schema\DtoInspector;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use PHPUnit\Framework\TestCase;

final class SpatieCastPortTest extends TestCase
{
    public function test_hydrator_casts_backed_enum_values(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(SpatieCastStateDto::class),
            ['status' => 'draft']
        );

        $this->assertSame(SpatieCastStatus::Draft, $hydrated['status']);
    }

    public function test_invalid_backed_enum_input_fails_validation(): void
    {
        try {
            SpatieCastStateDto::fromArray([
                'status' => 'archived',
            ]);

            $this->fail('Expected invalid backed enum input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors);
        }
    }

    public function test_hydrator_casts_date_format_values(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(SpatieCastFormattedDateDto::class),
            ['createdAt' => '19-05-1994 00:00:00']
        );

        $this->assertInstanceOf(CarbonImmutable::class, $hydrated['createdAt']);
        $this->assertSame('1994-05-19 00:00:00', $hydrated['createdAt']->format('Y-m-d H:i:s'));
    }

    public function test_invalid_date_format_input_fails_validation(): void
    {
        try {
            SpatieCastFormattedDateDto::fromArray([
                'createdAt' => '1994-05-19',
            ]);

            $this->fail('Expected invalid date format input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('createdAt', $e->errors);
        }
    }

    public function test_string_integer_values_are_not_broadly_cast_to_int(): void
    {
        try {
            SpatieCastIntegerDto::fromArray([
                'amount' => '42',
            ]);

            $this->fail('Expected string integer input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors);
        }
    }

    public function test_string_boolean_values_are_not_broadly_cast_to_bool(): void
    {
        try {
            SpatieCastBooleanDto::fromArray([
                'enabled' => 'true',
            ]);

            $this->fail('Expected string boolean input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enabled', $e->errors);
        }
    }

    public function test_hydrator_passes_through_existing_backed_enum_instance(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(SpatieCastStateDto::class),
            ['status' => SpatieCastStatus::Published]
        );

        $this->assertSame(SpatieCastStatus::Published, $hydrated['status']);
    }

    public function test_backed_enum_rejects_case_name_instead_of_backing_value(): void
    {
        // The backing value is 'draft'; the case name 'Draft' must not be guessed.
        try {
            SpatieCastStateDto::fromArray([
                'status' => 'Draft',
            ]);

            $this->fail('Expected the enum case name to be rejected as input.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors);
        }
    }

    public function test_backed_enum_rejects_non_scalar_value(): void
    {
        try {
            SpatieCastStateDto::fromArray([
                'status' => ['draft'],
            ]);

            $this->fail('Expected a non-scalar enum input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors);
        }
    }

    public function test_hydrator_casts_int_backed_enum_from_backing_int(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(SpatieCastIntStateDto::class),
            ['level' => 2]
        );

        $this->assertSame(SpatieCastLevel::High, $hydrated['level']);
    }

    public function test_int_backed_enum_rejects_numeric_string_backing_value(): void
    {
        // A numeric string that looks like a valid backing value must not be coerced.
        try {
            SpatieCastIntStateDto::fromArray([
                'level' => '1',
            ]);

            $this->fail('Expected a numeric string to be rejected for an int-backed enum.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('level', $e->errors);
        }
    }

    public function test_hydrator_casts_carbon_with_date_format(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(SpatieCastCarbonDateDto::class),
            ['createdAt' => '19-05-1994 00:00:00']
        );

        $this->assertInstanceOf(Carbon::class, $hydrated['createdAt']);
        $this->assertSame('1994-05-19 00:00:00', $hydrated['createdAt']->format('Y-m-d H:i:s'));
    }

    public function test_hydrator_casts_datetime_with_date_format(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(SpatieCastDateTimeDateDto::class),
            ['createdAt' => '19-05-1994 00:00:00']
        );

        $this->assertInstanceOf(DateTime::class, $hydrated['createdAt']);
        $this->assertSame('1994-05-19 00:00:00', $hydrated['createdAt']->format('Y-m-d H:i:s'));
    }
}

enum SpatieCastStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class SpatieCastStateDto
{
    use AsDto;

    public function __construct(public readonly SpatieCastStatus $status) {}
}

final class SpatieCastFormattedDateDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('d-m-Y H:i:s')]
        public readonly CarbonImmutable $createdAt,
    ) {}
}

final class SpatieCastIntegerDto
{
    use AsDto;

    public function __construct(public readonly int $amount) {}
}

final class SpatieCastBooleanDto
{
    use AsDto;

    public function __construct(public readonly bool $enabled) {}
}

enum SpatieCastLevel: int
{
    case Low = 1;
    case High = 2;
}

final class SpatieCastIntStateDto
{
    use AsDto;

    public function __construct(public readonly SpatieCastLevel $level) {}
}

final class SpatieCastCarbonDateDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('d-m-Y H:i:s')]
        public readonly Carbon $createdAt,
    ) {}
}

final class SpatieCastDateTimeDateDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('d-m-Y H:i:s')]
        public readonly DateTime $createdAt,
    ) {}
}
