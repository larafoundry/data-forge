<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Concerns\AsDto;

class InvalidArrayOfArrayDto
{
    use AsDto;

    public function __construct(
        #[ArrayOf(BasicDto::class)]
        public readonly array $people
    ) {}
}
