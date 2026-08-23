<?php

declare(strict_types=1);

namespace Tests\Unit\Hydration\Objects;

final class PrivateConstructObject
{
    public function __construct(private readonly string $value) {}

    public function getValue(): string
    {
        return $this->value;
    }
}
