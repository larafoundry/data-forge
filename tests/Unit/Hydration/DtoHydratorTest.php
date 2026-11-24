<?php

declare(strict_types=1);

namespace Tests\Unit\Hydration;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Hydration\DtoHydrator;
use Axiom\DataForge\Schema\DtoInspector;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class DtoHydratorTest extends TestCase
{
    public function test_hydrate_casts_backed_enums_and_date_like_values(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(HydratorCastingFixtureDto::class),
            [
                'createdAt' => '2024-01-02 03:04:05',
                'status' => 'draft',
            ]
        );

        $this->assertSame(HydratorStatus::Draft, $hydrated['status']);
        $this->assertInstanceOf(DateTimeImmutable::class, $hydrated['createdAt']);
        $this->assertSame('2024-01-02 03:04:05', $hydrated['createdAt']->format('Y-m-d H:i:s'));
    }

    public function test_hydrate_uses_date_format_attributes(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(HydratorFormattedDateFixtureDto::class),
            [
                'date' => '31/12/2024',
            ]
        );

        $this->assertInstanceOf(CarbonImmutable::class, $hydrated['date']);
        $this->assertSame('2024-12-31', $hydrated['date']->format('Y-m-d'));
    }

    public function test_hydrate_date_format_resets_fields_missing_from_the_format(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(HydratorDateOnlyFormatFixtureDto::class),
            [
                'carbon' => '31/12/2024',
                'immutable' => '31/12/2024',
                'mutable' => '31/12/2024',
                'zoned' => '31/12/2024',
            ]
        );

        foreach (['carbon', 'immutable', 'mutable', 'zoned'] as $key) {
            $this->assertInstanceOf(DateTimeInterface::class, $hydrated[$key]);
            $this->assertSame('2024-12-31 00:00:00.000000', $hydrated[$key]->format('Y-m-d H:i:s.u'), $key);
        }

        $this->assertInstanceOf(DateTimeInterface::class, $hydrated['zoned']);
        $this->assertSame('Asia/Ho_Chi_Minh', $hydrated['zoned']->getTimezone()->getName());
    }

    public function test_hydrate_builds_nested_dto_collections(): void
    {
        $hydrated = DtoHydrator::hydrate(
            new DtoInspector(HydratorCollectionFixtureDto::class),
            [
                'children' => [
                    ['name' => 'Ada'],
                    ['name' => 'Grace'],
                ],
            ]
        );

        $this->assertInstanceOf(Collection::class, $hydrated['children']);
        $this->assertContainsOnlyInstancesOf(HydratorChildFixtureDto::class, $hydrated['children']);
        $this->assertSame('Ada', $hydrated['children'][0]->name);
        $this->assertSame('Grace', $hydrated['children'][1]->name);
    }

    public function test_hydrate_rejects_arrayof_fields_that_are_not_lists(): void
    {
        $this->expectException(ValidationException::class);

        DtoHydrator::hydrate(
            new DtoInspector(HydratorCollectionFixtureDto::class),
            [
                'children' => [
                    'first' => ['name' => 'Ada'],
                ],
            ]
        );
    }
}

enum HydratorStatus: string
{
    case Draft = 'draft';
}

final class HydratorCastingFixtureDto
{
    use AsDto;

    public function __construct(
        public readonly HydratorStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}

final class HydratorFormattedDateFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('d/m/Y')]
        public readonly CarbonImmutable $date,
    ) {}
}

final class HydratorDateOnlyFormatFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('d/m/Y')]
        public readonly CarbonImmutable $carbon,
        #[DateFormat('d/m/Y')]
        public readonly DateTimeImmutable $immutable,
        #[DateFormat('d/m/Y')]
        public readonly DateTime $mutable,
        #[DateFormat('d/m/Y', 'Asia/Ho_Chi_Minh')]
        public readonly DateTimeImmutable $zoned,
    ) {}
}

final class HydratorChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class HydratorCollectionFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, HydratorChildFixtureDto>  $children
     */
    public function __construct(
        public readonly Collection $children,
    ) {}
}
