<?php

declare(strict_types=1);

namespace Tests\Unit\Casters;

use Axiom\DataForge\Casters\CarbonCaster;
use Carbon\CarbonInterface;
use DateTime;
use PHPUnit\Framework\TestCase;

class CarbonCasterTest extends TestCase
{
    public function test_cast_from_string(): void
    {
        $caster = new CarbonCaster();
        $result = $caster->cast('2023-01-01 12:00:00');

        $this->assertInstanceOf(CarbonInterface::class, $result);
        $this->assertSame('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_cast_from_datetime(): void
    {
        $caster = new CarbonCaster();
        $dt = new DateTime('2023-02-02 10:00:00');
        $result = $caster->cast($dt);

        $this->assertInstanceOf(CarbonInterface::class, $result);
        $this->assertSame('2023-02-02 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_cast_invalid_returns_null(): void
    {
        $caster = new CarbonCaster();
        $this->assertNull($caster->cast([]));
    }
}
