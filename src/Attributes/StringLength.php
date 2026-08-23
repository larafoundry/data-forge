<?php

declare(strict_types=1);

namespace Axiom\DataForge\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class StringLength
{
    public function __construct(
        public readonly int $min = 0,
        public readonly int $max = 255,
    ) {
        if ($min < 0) {
            throw new InvalidArgumentException('Min value cannot be less than 0');
        }
        if ($max < $min) {
            throw new InvalidArgumentException('Max value cannot be less than min value');
        }
    }
}
