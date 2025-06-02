<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto\Objects;

use Axiom\DataForge\Concerns\AsDto;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;

final class ComplexObject
{
    use AsDto;

    public function __construct(
        public readonly EnumType $enumType,
        public readonly Carbon $carbon,
        public readonly CarbonImmutable $carbonImmutable,
        public readonly DateTimeImmutable $dateTimeImmutable,
        public readonly DateTime $dateTime,
    ) {}
}
