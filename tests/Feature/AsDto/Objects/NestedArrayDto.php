<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

use Axiom\DataForge\Concerns\AsDto;

class NestedArrayDto
{
    use AsDto;

    /**
     * @param  BasicDto[]  $people
     */
    public function __construct(
        public readonly array $people
    ) {}
}
