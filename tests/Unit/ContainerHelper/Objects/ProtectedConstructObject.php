<?php

declare(strict_types=1);

namespace Tests\Unit\ContainerHelper\Objects;

final class ProtectedConstructObject
{
    public function __construct(protected readonly string $value) {}

    public function getValue(): string
    {
        return $this->value;
    }
}
