<?php

declare(strict_types=1);

namespace Ws\DataBridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateFormat
{
    public function __construct(
        public string $format = 'Y-m-d',
        public ?string $timezone = null,
    ) {}
}
