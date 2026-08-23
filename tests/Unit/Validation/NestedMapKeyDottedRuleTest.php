<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use PHPUnit\Framework\TestCase;

final class NestedMapKeyDottedRuleTest extends TestCase
{
    public function test_parent_dotted_required_rule_uses_nested_dto_mapkey(): void
    {
        $dto = NestedMapKeyDottedRuleParentFixtureDto::fromArray([
            'child' => [
                'first_name' => 'Ada',
            ],
        ]);

        $this->assertSame('Ada', $dto->child->firstName);
    }
}

final class NestedMapKeyDottedRuleChildFixtureDto
{
    use AsDto;

    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,
    ) {}
}

final class NestedMapKeyDottedRuleParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly NestedMapKeyDottedRuleChildFixtureDto $child) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'child.firstName' => 'required',
        ];
    }
}
