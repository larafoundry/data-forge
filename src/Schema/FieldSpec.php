<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use ReflectionParameter;
use ReflectionProperty;

final class FieldSpec
{
    /**
     * @param  class-string|null  $arrayItemClass
     */
    public function __construct(
        public readonly string $name,
        public readonly string $inputKey,
        public readonly TypeSpec $type,
        public readonly bool $required,
        public readonly bool $nullable,
        public readonly bool $hasDefault,
        public readonly mixed $defaultValue,
        public readonly ?string $arrayItemClass,
        public readonly ?ReflectionProperty $property,
        public readonly ?ReflectionParameter $parameter,
    ) {}
}
