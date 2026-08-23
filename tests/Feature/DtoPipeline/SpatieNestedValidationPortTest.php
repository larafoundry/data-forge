<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SpatieNestedValidationPortTest extends TestCase
{
    public function test_nested_dto_missing_required_field_reports_dotted_path(): void
    {
        try {
            SpatiePortNestedProfileParentDto::fromArray([
                'profile' => [
                    'email' => 'ada@example.com',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profile.name', $e->errors);
        }
    }

    public function test_nested_collection_item_missing_required_field_reports_indexed_dotted_path(): void
    {
        try {
            SpatiePortNestedCollectionParentDto::fromArray([
                'profiles' => [
                    [
                        'email' => 'ada@example.com',
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profiles.0.name', $e->errors);
        }
    }

    public function test_mapped_root_and_nested_validation_errors_render_external_input_keys(): void
    {
        try {
            SpatiePortMappedParentDto::fromArray([
                'profile_data' => [
                    'given_name' => 'Ada',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('title_text', $e->errors);
            $this->assertArrayNotHasKey('title', $e->errors);
        }

        try {
            SpatiePortMappedParentDto::fromArray([
                'title_text' => 'Engineer',
                'profile_data' => [
                    'given_name' => 123,
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profile_data.given_name', $e->errors);
            $this->assertArrayNotHasKey('profile.name', $e->errors);
            $this->assertArrayNotHasKey('profile_data.name', $e->errors);
        }
    }
}

final class SpatiePortNestedProfileParentDto
{
    use AsDto;

    public function __construct(public readonly SpatiePortProfileDto $profile) {}
}

final class SpatiePortNestedCollectionParentDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatiePortProfileDto>  $profiles
     */
    public function __construct(
        #[ArrayOf(SpatiePortProfileDto::class)]
        public readonly Collection $profiles,
    ) {}
}

final class SpatiePortMappedParentDto
{
    use AsDto;

    public function __construct(
        #[MapKey('title_text')]
        public readonly string $title,
        #[MapKey('profile_data')]
        public readonly SpatiePortMappedProfileDto $profile,
    ) {}
}

final class SpatiePortProfileDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}

final class SpatiePortMappedProfileDto
{
    use AsDto;

    public function __construct(
        #[MapKey('given_name')]
        public readonly string $name,
    ) {}
}
