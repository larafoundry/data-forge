<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters;

use Axiom\DataForge\Casters\Contracts\ICaster;

/**
 * Class BooleanCaster
 *
 * @implements ICaster<bool>
 */
class BooleanCaster implements ICaster
{
    public function cast(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($filtered !== null) {
                return $filtered;
            }
        }

        return null;
    }
}
