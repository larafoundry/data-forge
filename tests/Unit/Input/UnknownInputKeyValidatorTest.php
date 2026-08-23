<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Input\UnknownInputKeyValidator;
use Axiom\DataForge\Schema\DtoInspector;
use PHPUnit\Framework\TestCase;

final class UnknownInputKeyValidatorTest extends TestCase
{
    public function test_strict_root_rejects_unknown_raw_input_keys(): void
    {
        try {
            UnknownInputKeyValidator::assertNoUnknownKeys(
                new DtoInspector(StrictMappedInputFixtureDto::class),
                [
                    'first_name' => 'Ada',
                    'firstName' => 'unknown canonical key',
                ],
                rejectsUnknownInputKeys: true
            );

            $this->fail('Unknown key validation should have failed.');
        } catch (UnknownInputKeyException $e) {
            $this->assertArrayHasKey('firstName', $e->errors);
        }
    }

    public function test_flexible_root_still_checks_nested_strict_dtos(): void
    {
        try {
            UnknownInputKeyValidator::assertNoUnknownKeys(
                new DtoInspector(FlexibleParentWithStrictCollectionFixtureDto::class),
                [
                    'children' => [
                        [
                            'name' => 'Ada',
                            'extra' => 'rejected by strict child',
                        ],
                    ],
                    'root_extra' => 'allowed by flexible root',
                ],
                rejectsUnknownInputKeys: false
            );

            $this->fail('Nested strict unknown key validation should have failed.');
        } catch (UnknownInputKeyException $e) {
            $this->assertArrayHasKey('children.0.extra', $e->errors);
            $this->assertArrayNotHasKey('root_extra', $e->errors);
        }
    }
}

final class StrictMappedInputFixtureDto
{
    use AsStrictInputDto;

    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,
    ) {}
}

final class StrictCollectionChildFixtureDto
{
    use AsStrictInputDto;

    public function __construct(public readonly string $name) {}
}

final class FlexibleParentWithStrictCollectionFixtureDto
{
    use AsDto;

    /**
     * @param  array<int, StrictCollectionChildFixtureDto>  $children
     */
    public function __construct(
        #[ArrayOf(StrictCollectionChildFixtureDto::class)]
        public readonly array $children,
    ) {}
}
