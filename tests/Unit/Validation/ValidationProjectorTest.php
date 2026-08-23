<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Validation\ValidationProjector;
use PHPUnit\Framework\TestCase;

final class ValidationProjectorTest extends TestCase
{
    public function test_project_normalizes_nested_dto_arrays_to_canonical_keys(): void
    {
        $projected = ValidationProjector::project(
            new DtoInspector(ProjectorParentFixtureDto::class),
            [
                'child' => [
                    'full_name' => 'Ada Lovelace',
                ],
            ]
        );

        $this->assertSame([
            'child' => [
                'name' => 'Ada Lovelace',
            ],
        ], $projected);
    }

    public function test_project_converts_backed_enum_instances_to_backing_values(): void
    {
        $projected = ValidationProjector::project(
            new DtoInspector(ProjectorStatusFixtureDto::class),
            [
                'status' => ProjectorStatus::Draft,
            ]
        );

        $this->assertSame([
            'status' => 'draft',
        ], $projected);
    }
}

final class ProjectorParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly ProjectorChildFixtureDto $child) {}
}

final class ProjectorChildFixtureDto
{
    use AsDto;

    public function __construct(
        #[MapKey('full_name')]
        public readonly string $name,
    ) {}
}

enum ProjectorStatus: string
{
    case Draft = 'draft';
}

final class ProjectorStatusFixtureDto
{
    use AsDto;

    public function __construct(public readonly ProjectorStatus $status) {}
}
