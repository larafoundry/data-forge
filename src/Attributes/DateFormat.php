<?php

declare(strict_types=1);

namespace Axiom\DataForge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateFormat
{
    public function __construct(
        public readonly string $format = 'Y-m-d',
        public readonly ?string $timezone = null,
    ) {}
}
