<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns\Objects;

enum SimpleEnum: string
{
    case ONE = 'one';
    case TWO = 'two';
    case THREE = 'three';
} 