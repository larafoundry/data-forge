<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto\Objects;

use Ws\DataBridge\Concerns\AsDto;

class NestedDto
{
    use AsDto;

    public function __construct(
        public readonly string $title,
        public readonly BasicDto $person
    ) {
    }
}
