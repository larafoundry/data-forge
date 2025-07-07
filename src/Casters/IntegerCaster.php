<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters;

use Axiom\DataForge\Casters\Contracts\ICaster;

/**
 * Class IntegerCaster
 * @implements ICaster<int>
 */
class IntegerCaster implements ICaster
{
    public function cast(mixed $value): ?int
    {
        return (is_numeric($value) || is_bool($value)) ? (int) $value : null;
    }
}
