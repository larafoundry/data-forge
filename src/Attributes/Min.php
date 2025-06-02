<?php

declare(strict_types=1);

namespace Axiom\DataForge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Min
{
    public function __construct(
        public readonly int|float $value
    ) {}
}
