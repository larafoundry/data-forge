<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Input\InputMapper;
use Axiom\DataForge\Schema\DtoInspector;
use PHPUnit\Framework\TestCase;

final class InputMapperTest extends TestCase
{
    public function test_normalize_maps_external_input_keys_to_canonical_keys(): void
    {
        $normalized = InputMapper::normalize(
            new DtoInspector(MappedInputFixtureDto::class),
            [
                'first_name' => 'Ada',
                'firstName' => 'ignored',
                'age' => 37,
                'extra' => 'ignored',
            ]
        );

        $this->assertSame([
            'age' => 37,
            'firstName' => 'Ada',
        ], $normalized);
    }
}

final class MappedInputFixtureDto
{
    use AsDto;

    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,
        public readonly int $age,
    ) {}
}
