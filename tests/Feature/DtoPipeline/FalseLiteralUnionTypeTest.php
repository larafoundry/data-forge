<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Concerns\AsDto;
use PHPUnit\Framework\TestCase;

final class FalseLiteralUnionTypeTest extends TestCase
{
    public function test_false_literal_union_accepts_false_value(): void
    {
        $dto = FalseLiteralUnionFixtureDto::fromArray([
            'value' => false,
        ]);

        $this->assertFalse($dto->value);
    }
}

final class FalseLiteralUnionFixtureDto
{
    use AsDto;

    public function __construct(public readonly string|false $value) {}
}
