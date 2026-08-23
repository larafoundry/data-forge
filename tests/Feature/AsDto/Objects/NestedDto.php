<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

use Axiom\DataForge\Concerns\AsDto;

class NestedDto
{
    use AsDto;

    public function __construct(
        public readonly string $title,
        public readonly BasicDto $person
    ) {}
}
