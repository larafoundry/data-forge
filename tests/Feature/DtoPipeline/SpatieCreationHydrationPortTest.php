<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SpatieCreationHydrationPortTest extends TestCase
{
    public function test_constructs_promoted_readonly_and_public_default_properties(): void
    {
        $dto = SpatieCreationProfileDto::fromArray([
            'name' => 'Ada',
            'handle' => 'ada',
        ]);

        $this->assertSame('Ada', $dto->name);
        $this->assertSame('reader', $dto->role);
        $this->assertSame('ada', $dto->handle);
        $this->assertSame('en_US', $dto->locale);
    }

    public function test_input_can_override_constructor_and_public_property_defaults(): void
    {
        $dto = SpatieCreationProfileDto::fromArray([
            'name' => 'Grace',
            'role' => 'admin',
            'handle' => 'grace',
            'locale' => 'nl_BE',
        ]);

        $this->assertSame('Grace', $dto->name);
        $this->assertSame('admin', $dto->role);
        $this->assertSame('grace', $dto->handle);
        $this->assertSame('nl_BE', $dto->locale);
    }

    public function test_omitted_nullable_defaults_remain_null(): void
    {
        $dto = SpatieCreationNullableDefaultsDto::fromArray([
            'name' => 'Ada',
        ]);

        $this->assertSame('Ada', $dto->name);
        $this->assertNull($dto->nickname);
        $this->assertNull($dto->timezone);
    }

    public function test_falsey_values_are_preserved_during_creation(): void
    {
        $dto = SpatieCreationFalseyValuesDto::fromArray([
            'enabled' => false,
            'count' => 0,
            'label' => '0',
            'items' => [0],
        ]);

        $this->assertFalse($dto->enabled);
        $this->assertSame(0, $dto->count);
        $this->assertSame('0', $dto->label);
        $this->assertSame([0], $dto->items);
    }

    public function test_missing_required_constructor_data_fails_validation_before_instantiation(): void
    {
        try {
            SpatieCreationProfileDto::fromArray([
                'handle' => 'ada',
            ]);

            $this->fail('Expected missing constructor data to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_hydrates_nested_dto_from_array(): void
    {
        $dto = SpatiePortAccountDto::fromArray([
            'profile' => [
                'name' => 'Ada',
                'handle' => 'ada',
            ],
        ]);

        $this->assertInstanceOf(SpatieCreationProfileDto::class, $dto->profile);
        $this->assertSame('Ada', $dto->profile->name);
        $this->assertSame('ada', $dto->profile->handle);
    }

    public function test_preserves_already_hydrated_nested_dto_instances(): void
    {
        $profile = SpatieCreationProfileDto::fromArray([
            'name' => 'Ada',
            'handle' => 'ada',
        ]);

        $dto = SpatiePortAccountDto::fromArray([
            'profile' => $profile,
        ]);

        $this->assertSame($profile, $dto->profile);
    }

    public function test_preserves_constructor_built_nested_dto_instances_without_rerunning_child_static_rules(): void
    {
        $profile = new SpatieTrustedProfileDto('Al');

        $dto = SpatieTrustedAccountDto::fromArray([
            'profile' => $profile,
        ]);

        $this->assertSame($profile, $dto->profile);
        $this->assertSame('Al', $dto->profile->name);
    }

    public function test_hydrates_list_like_arrayof_collections_to_laravel_collection(): void
    {
        $dto = SpatiePortTeamDto::fromArray([
            'profiles' => [
                [
                    'name' => 'Ada',
                    'handle' => 'ada',
                ],
                [
                    'name' => 'Grace',
                    'handle' => 'grace',
                ],
            ],
        ]);

        $this->assertInstanceOf(Collection::class, $dto->profiles);
        $this->assertContainsOnlyInstancesOf(SpatieCreationProfileDto::class, $dto->profiles);
        $this->assertSame('Ada', $dto->profiles[0]->name);
        $this->assertSame('Grace', $dto->profiles[1]->name);
    }

    public function test_arrayof_collection_preserves_already_hydrated_items(): void
    {
        $profile = SpatieCreationProfileDto::fromArray([
            'name' => 'Ada',
            'handle' => 'ada',
        ]);

        $dto = SpatiePortTeamDto::fromArray([
            'profiles' => [
                $profile,
                [
                    'name' => 'Grace',
                    'handle' => 'grace',
                ],
            ],
        ]);

        $this->assertSame($profile, $dto->profiles[0]);
        $this->assertInstanceOf(SpatieCreationProfileDto::class, $dto->profiles[1]);
        $this->assertSame('Grace', $dto->profiles[1]->name);
    }

    public function test_arrayof_collection_preserves_constructor_built_items_without_rerunning_child_static_rules(): void
    {
        $profile = new SpatieTrustedProfileDto('Al');

        $dto = SpatieTrustedTeamDto::fromArray([
            'profiles' => [
                $profile,
            ],
        ]);

        $this->assertSame($profile, $dto->profiles[0]);
        $this->assertSame('Al', $dto->profiles[0]->name);
    }

    public function test_arrayof_collection_rejects_associative_arrays(): void
    {
        try {
            SpatiePortTeamDto::fromArray([
                'profiles' => [
                    'first' => [
                        'name' => 'Ada',
                        'handle' => 'ada',
                    ],
                ],
            ]);

            $this->fail('Expected associative ArrayOf input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profiles', $e->errors);
        }
    }

    public function test_nested_child_from_array_override_is_not_used_by_nested_hydration(): void
    {
        $dto = SpatiePortParentWithCustomRootChildDto::fromArray([
            'child' => [
                'name' => 'Nested input',
            ],
        ]);

        $this->assertSame('Nested input', $dto->child->name);
    }
}

final class SpatieCreationProfileDto
{
    use AsDto;

    public string $handle;

    public string $locale = 'en_US';

    public function __construct(
        public readonly string $name,
        public readonly string $role = 'reader',
    ) {}
}

final class SpatiePortAccountDto
{
    use AsDto;

    public function __construct(public readonly SpatieCreationProfileDto $profile) {}
}

final class SpatieTrustedAccountDto
{
    use AsDto;

    public function __construct(public readonly SpatieTrustedProfileDto $profile) {}
}

final class SpatiePortTeamDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieCreationProfileDto>  $profiles
     */
    public function __construct(
        #[ArrayOf(SpatieCreationProfileDto::class)]
        public readonly Collection $profiles,
    ) {}
}

final class SpatieTrustedTeamDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieTrustedProfileDto>  $profiles
     */
    public function __construct(
        #[ArrayOf(SpatieTrustedProfileDto::class)]
        public readonly Collection $profiles,
    ) {}
}

final class SpatiePortChildWithCustomRootFromArrayDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): static
    {
        return new self('root override');
    }
}

final class SpatieCreationNullableDefaultsDto
{
    use AsDto;

    public ?string $timezone = null;

    public function __construct(
        public readonly string $name,
        public readonly ?string $nickname = null,
    ) {}
}

final class SpatieCreationFalseyValuesDto
{
    use AsDto;

    /**
     * @param  array<int, int>  $items
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly int $count,
        public readonly string $label,
        public readonly array $items,
    ) {}
}

final class SpatieTrustedProfileDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'min:3',
        ];
    }
}

final class SpatiePortParentWithCustomRootChildDto
{
    use AsDto;

    public function __construct(public readonly SpatiePortChildWithCustomRootFromArrayDto $child) {}
}
