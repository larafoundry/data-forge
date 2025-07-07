<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters\Contracts;

/**
 * @template T
 */
interface ICaster
{
    /**
     * @return T|null
     */
    public function cast(mixed $value);
}
