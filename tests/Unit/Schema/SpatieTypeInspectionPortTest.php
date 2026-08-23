<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\TypeSpec;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SpatieTypeInspectionPortTest extends TestCase
{
    public function test_named_nullable_and_union_type_metadata_stays_explicit(): void
    {
        $profile = $this->typeSpecFor('profile');
        $this->assertSame(SpatieTypeProfileFixtureDto::class, $profile->namedClass());
        $this->assertFalse($profile->allowsNull());
        $this->assertTrue($profile->accepts(new SpatieTypeProfileFixtureDto('Ada')));
        $this->assertFalse($profile->accepts(['name' => 'Ada']));

        $nullableProfile = $this->typeSpecFor('nullableProfile');
        $this->assertSame(SpatieTypeProfileFixtureDto::class, $nullableProfile->namedClass());
        $this->assertTrue($nullableProfile->allowsNull());
        $this->assertTrue($nullableProfile->accepts(null));

        $ambiguous = $this->typeSpecFor('ambiguousProfile');
        $this->assertNull($ambiguous->namedClass());
        $this->assertTrue($ambiguous->accepts(new SpatieTypeProfileFixtureDto('Ada')));
        $this->assertTrue($ambiguous->accepts(new SpatieTypeAdminFixtureDto('Grace')));
        $this->assertFalse($ambiguous->accepts(['name' => 'Ada']));
    }

    public function test_ambiguous_dto_unions_do_not_hydrate_from_nested_arrays(): void
    {
        try {
            SpatieTypeUnionContainerFixtureDto::fromArray([
                'target' => [
                    'name' => 'Ada',
                ],
            ]);

            $this->fail('Expected ambiguous DTO union input to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target', $e->errors);
        }
    }

    public function test_false_literal_union_accepts_only_false_or_named_scalar_branch(): void
    {
        $type = $this->typeSpecFor('falseOrString');

        $this->assertTrue($type->accepts(false));
        $this->assertTrue($type->accepts('archived'));
        $this->assertFalse($type->accepts(true));
        $this->assertFalse($type->accepts(0));
    }

    private function typeSpecFor(string $property): TypeSpec
    {
        return new TypeSpec((new ReflectionProperty(SpatieTypeInspectionFixture::class, $property))->getType());
    }
}

final class SpatieTypeInspectionFixture
{
    public SpatieTypeProfileFixtureDto $profile;

    public ?SpatieTypeProfileFixtureDto $nullableProfile;

    public SpatieTypeProfileFixtureDto|SpatieTypeAdminFixtureDto $ambiguousProfile;

    public false|string $falseOrString;
}

final class SpatieTypeProfileFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class SpatieTypeAdminFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class SpatieTypeUnionContainerFixtureDto
{
    use AsDto;

    public function __construct(
        public readonly SpatieTypeProfileFixtureDto|SpatieTypeAdminFixtureDto $target,
    ) {}
}
