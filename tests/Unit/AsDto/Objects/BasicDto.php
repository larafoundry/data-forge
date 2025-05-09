<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto\Objects;

use Ws\DataBridge\Concerns\AsDto;

class BasicDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly ?string $email = null
    ) {
    }
}
