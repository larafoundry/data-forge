<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SpatieValidationMessagesPortTest extends TestCase
{
    public function test_nested_child_static_messages_are_prefixed_to_nested_path(): void
    {
        try {
            SpatieMessageNestedParentDto::fromArray([
                'profile' => [
                    'song' => 'Never Gonna Give You Up',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['Fix the nested name.'], $e->errors['profile.name']);
        }
    }

    public function test_collection_child_static_messages_are_prefixed_with_item_index(): void
    {
        try {
            SpatieMessageCollectionParentDto::fromArray([
                'profiles' => [
                    ['song' => 'Never Gonna Give You Up'],
                    ['song' => 'Together Forever'],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['Fix the nested name.'], $e->errors['profiles.0.name']);
            $this->assertSame(['Fix the nested name.'], $e->errors['profiles.1.name']);
        }
    }

    public function test_parent_nested_custom_messages_are_rendered_on_external_paths(): void
    {
        try {
            SpatieMessageParentOverridesDto::fromArray([
                'profile' => [
                    'song' => 'Never Gonna Give You Up',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['Parent says fix the profile name.'], $e->errors['profile.name']);
        }
    }

    public function test_mapped_child_static_messages_are_rendered_on_external_paths(): void
    {
        try {
            SpatieMessageMappedParentDto::fromArray([
                'profile_data' => [
                    'song' => 'Never Gonna Give You Up',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['Fix the mapped nested name.'], $e->errors['profile_data.given_name']);
            $this->assertArrayNotHasKey('profile.name', $e->errors);
        }
    }

    public function test_parent_wildcard_collection_message_is_applied_to_item_path(): void
    {
        try {
            SpatieMessageWildcardCollectionParentDto::fromArray([
                'profiles' => [
                    ['song' => 'Never Gonna Give You Up'],
                    ['song' => 'Together Forever'],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['Parent wildcard says fix the profile name.'], $e->errors['profiles.0.name']);
            $this->assertSame(['Parent wildcard says fix the profile name.'], $e->errors['profiles.1.name']);
        }
    }

    public function test_double_nested_collection_message_paths_are_scoped_to_item_paths(): void
    {
        try {
            SpatieMessageTeamDirectoryDto::fromArray([
                'teams' => [
                    [
                        'members' => [
                            ['song' => 'Never Gonna Give You Up'],
                            ['song' => 'Together Forever'],
                        ],
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['Directory says fix the member name.'], $e->errors['teams.0.members.0.full_name']);
            $this->assertSame(['Directory says fix the member name.'], $e->errors['teams.0.members.1.full_name']);
            $this->assertArrayNotHasKey('teams.0.members.0.fullName', $e->errors);
        }
    }
}

final class SpatieMessageNestedParentDto
{
    use AsDto;

    public function __construct(public readonly SpatieMessageProfileDto $profile) {}
}

final class SpatieMessageCollectionParentDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieMessageProfileDto>  $profiles
     */
    public function __construct(
        #[ArrayOf(SpatieMessageProfileDto::class)]
        public readonly Collection $profiles,
    ) {}
}

final class SpatieMessageParentOverridesDto
{
    use AsDto;

    public function __construct(public readonly SpatieMessagePlainProfileDto $profile) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'profile.name.required' => 'Parent says fix the profile name.',
        ];
    }
}

final class SpatieMessageMappedParentDto
{
    use AsDto;

    public function __construct(
        #[MapKey('profile_data')]
        public readonly SpatieMessageMappedProfileDto $profile,
    ) {}
}

final class SpatieMessageWildcardCollectionParentDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieMessagePlainProfileDto>  $profiles
     */
    public function __construct(
        #[ArrayOf(SpatieMessagePlainProfileDto::class)]
        public readonly Collection $profiles,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'profiles.*.name.required' => 'Parent wildcard says fix the profile name.',
        ];
    }
}

final class SpatieMessageTeamDirectoryDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieMessageTeamDto>  $teams
     */
    public function __construct(
        #[ArrayOf(SpatieMessageTeamDto::class)]
        public readonly Collection $teams,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'teams.*.members.*.fullName.required' => 'Directory says fix the member name.',
        ];
    }
}

final class SpatieMessageTeamDto
{
    use AsDto;

    /**
     * @param  Collection<int, SpatieMessageMemberDto>  $members
     */
    public function __construct(
        #[ArrayOf(SpatieMessageMemberDto::class)]
        public readonly Collection $members,
    ) {}
}

final class SpatieMessageMappedProfileDto
{
    use AsDto;

    public function __construct(
        #[MapKey('given_name')]
        public readonly string $name,
        public readonly string $song,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Fix the mapped nested name.',
        ];
    }
}

final class SpatieMessageMemberDto
{
    use AsDto;

    public function __construct(
        #[MapKey('full_name')]
        public readonly string $fullName,
        public readonly string $song,
    ) {}
}

final class SpatieMessageProfileDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly string $song,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Fix the nested name.',
        ];
    }
}

final class SpatieMessagePlainProfileDto
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly string $song,
    ) {}
}
