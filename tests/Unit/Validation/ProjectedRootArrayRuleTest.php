<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProjectedRootArrayRuleTest extends TestCase
{
    public function test_already_hydrated_nested_dto_does_not_satisfy_raw_array_rule(): void
    {
        $this->expectException(ValidationException::class);

        ProjectedRootArrayParentFixtureDto::fromArray([
            'child' => new ProjectedRootArrayChildFixtureDto('Ada'),
        ]);
    }
}

final class ProjectedRootArrayChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class ProjectedRootArrayParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly ProjectedRootArrayChildFixtureDto $child) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'child' => 'array',
            'child.name' => 'required|min:3',
        ];
    }
}
