<?php

declare(strict_types=1);

namespace Ws\DataBridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class MapKey
{
    public function __construct(
        public readonly string $key
    ) {}
}
