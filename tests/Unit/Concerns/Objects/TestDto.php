<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns\Objects;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Ws\DataBridge\Concerns\AsDto;

class TestDto
{
    use AsDto;
    
    public function __construct(
        public readonly DateTime $dateTime,
        public readonly DateTimeImmutable $dateTimeImmutable,
        public readonly Carbon $carbon,
        public readonly CarbonImmutable $carbonImmutable,
        public readonly string $name
    ) {
    }
} 