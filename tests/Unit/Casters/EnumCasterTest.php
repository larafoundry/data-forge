<?php

declare(strict_types=1);

namespace Tests\Unit\Casters;

use Axiom\DataForge\Casters\EnumCaster;
use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\EnumType;

class EnumCasterTest extends TestCase
{
    public function test_cast_from_string(): void
    {
        $caster = new EnumCaster(EnumType::class);

        $result = $caster->cast('required');

        $this->assertInstanceOf(EnumType::class, $result);
        $this->assertSame(EnumType::REQUIRED, $result);
    }

    public function test_cast_invalid_returns_null(): void
    {
        $caster = new EnumCaster(EnumType::class);
        $this->assertNull($caster->cast('invalid'));
    }
}
