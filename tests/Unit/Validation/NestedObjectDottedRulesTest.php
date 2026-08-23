<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class NestedObjectDottedRulesTest extends TestCase
{
    public function test_parent_dotted_rule_validates_already_hydrated_nested_dto_instance(): void
    {
        try {
            NestedObjectDottedRulesParentFixtureDto::fromArray([
                'child' => new NestedObjectDottedRulesChildFixtureDto('Al'),
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('child.name', $e->errors);
        }
    }
}

final class NestedObjectDottedRulesChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class NestedObjectDottedRulesParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly NestedObjectDottedRulesChildFixtureDto $child) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'child.name' => 'required|min:3',
        ];
    }
}
