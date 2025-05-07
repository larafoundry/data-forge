<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Objects;

class TestDto
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly ?string $email = null,
        public readonly ?string $url = null,
    ) {}
}
