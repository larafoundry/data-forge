<?php

declare(strict_types=1);

namespace Ws\DataBridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Min
{
    public function __construct(
        public readonly int|float $value
    )
    {
    }
}
