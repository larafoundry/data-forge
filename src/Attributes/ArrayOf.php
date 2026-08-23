<?php

declare(strict_types=1);

namespace Axiom\DataForge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ArrayOf
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public readonly string $class
    ) {}
}
