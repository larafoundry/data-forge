<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionItemDottedRulesTest extends TestCase
{
    public function test_collection_dotted_rule_validates_already_hydrated_item_instance(): void
    {
        try {
            CollectionItemDottedRulesParentFixtureDto::fromArray([
                'children' => [
                    new CollectionItemDottedRulesChildFixtureDto('Al'),
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('children.0.name', $e->errors);
        }
    }
}

final class CollectionItemDottedRulesChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class CollectionItemDottedRulesParentFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, CollectionItemDottedRulesChildFixtureDto>  $children
     */
    public function __construct(
        #[ArrayOf(CollectionItemDottedRulesChildFixtureDto::class)]
        public readonly Collection $children,
    ) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'children.*.name' => 'required|min:3',
        ];
    }
}
