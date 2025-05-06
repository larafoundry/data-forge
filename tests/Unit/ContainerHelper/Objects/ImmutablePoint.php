<?php

declare(strict_types=1);

namespace Tests\Unit\ContainerHelper\Objects;

class ImmutablePoint
{
    public function __construct(public readonly int $x, public readonly int $y)
    {
    }
}
