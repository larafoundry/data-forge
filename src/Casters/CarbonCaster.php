<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters;

use Axiom\DataForge\Casters\Contracts\ICaster;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Class CarbonCaster
 *
 * @implements ICaster<CarbonInterface>
 */
class CarbonCaster implements ICaster
{
    public function cast(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        return null;
    }
}
