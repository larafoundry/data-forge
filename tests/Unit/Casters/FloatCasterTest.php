<?php

declare(strict_types=1);

namespace Tests\Unit\Casters;

use Axiom\DataForge\Casters\FloatCaster;
use PHPUnit\Framework\TestCase;

class FloatCasterTest extends TestCase
{
    public function test_cast_basic_values(): void
    {
        $caster = new FloatCaster();

        $this->assertSame(1.5, $caster->cast(1.5));
        $this->assertSame(1.0, $caster->cast(1));
        $this->assertSame(1.0, $caster->cast(true));
        $this->assertNull($caster->cast('abc'));
    }
}
