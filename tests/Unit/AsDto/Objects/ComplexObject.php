<?php

namespace Tests\Unit\AsDto\Objects;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Ws\DataBridge\Concerns\AsDto;

final class ComplexObject
{
    use AsDto;

    public function __construct(
        public readonly EnumType $enumType,
        public readonly Carbon $carbon,
        public readonly CarbonImmutable $carbonImmutable,
        public readonly DateTimeImmutable $dateTimeImmutable,
        public readonly DateTime $dateTime,
    ) {
    }
}