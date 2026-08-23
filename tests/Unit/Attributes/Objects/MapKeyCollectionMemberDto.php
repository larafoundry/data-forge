<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes\Objects;

use Axiom\DataForge\Concerns\AsDto;

final class MapKeyCollectionMemberDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}
