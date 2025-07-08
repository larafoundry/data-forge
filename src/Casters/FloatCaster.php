<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters;

use Axiom\DataForge\Casters\Contracts\ICaster;

/**
 * Class FloatCaster
 *
 * @implements ICaster<float>
 */
class FloatCaster implements ICaster
{
    public function cast(mixed $value): ?float
    {
        return (is_numeric($value) || is_bool($value)) ? (float) $value : null;
    }
}
