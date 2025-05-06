<?php

declare(strict_types=1);

namespace Tests\Unit\ContainerHelper\Objects;

class User
{
    public function __construct(public string $name, public int $age)
    {
    }
}
