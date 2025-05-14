<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Ws\DataBridge\Concerns\AsDto;

/**
 * Simple DTO for testing FactoryManager and FactoryBatch
 */
final class TestDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly ?string $email = null,
        public readonly bool $active = true
    ) {}
}
