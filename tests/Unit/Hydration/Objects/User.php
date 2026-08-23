<?php

declare(strict_types=1);

namespace Tests\Unit\Hydration\Objects;

class User
{
    public function __construct(public string $name, public int $age) {}
}
