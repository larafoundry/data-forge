<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters;

use Axiom\DataForge\Casters\Contracts\ICaster;

/**
 * @implements ICaster<string>
 */
class StringCaster implements ICaster
{
    public function cast(mixed $value): ?string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_null($value)) {
            return '';
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;

    }
}
