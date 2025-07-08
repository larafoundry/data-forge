<?php

declare(strict_types=1);

namespace Tests\Unit\Casters;

use Axiom\DataForge\Casters\BooleanCaster;
use PHPUnit\Framework\TestCase;

class BooleanCasterTest extends TestCase
{
    public function test_cast_basic_values(): void
    {
        $caster = new BooleanCaster();

        $this->assertTrue($caster->cast(true));
        $this->assertFalse($caster->cast(false));
        $this->assertTrue($caster->cast(1));
        $this->assertFalse($caster->cast(0));
        $this->assertTrue($caster->cast('1'));
        $this->assertFalse($caster->cast('0'));
    }

    public function test_cast_invalid_returns_null(): void
    {
        $caster = new BooleanCaster();
        $this->assertNull($caster->cast('not-bool'));
        $this->assertNull($caster->cast([]));
    }
}
