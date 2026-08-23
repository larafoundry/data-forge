<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Attributes\Min;
use Axiom\DataForge\Attributes\StringLength;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Exceptions\ValidationException;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class CastingAndPropertyContractTest extends TestCase
{
    public function test_from_array_respects_static_dto_rules(): void
    {
        $this->expectException(ValidationException::class);

        RulesFixtureDto::fromArray([
            'age' => 17,
        ]);
    }

    public function test_invalid_date_cast_is_reported_as_validation_error(): void
    {
        $this->expectException(ValidationException::class);

        DateCastFixtureDto::fromArray([
            'createdAt' => 'not-a-date',
        ]);
    }

    public function test_int_backed_enum_accepts_backing_int_value(): void
    {
        $dto = IntBackedEnumFixtureDto::fromArray([
            'status' => 1,
        ]);

        $this->assertSame(FixtureIntStatus::Draft, $dto->status);
    }

    public function test_invalid_int_backed_enum_string_is_reported_as_validation_error(): void
    {
        $this->expectException(ValidationException::class);

        IntBackedEnumFixtureDto::fromArray([
            'status' => 'invalid',
        ]);
    }

    public function test_missing_nullable_public_property_is_initialized_to_null(): void
    {
        $dto = NullablePublicPropertyFixtureDto::fromArray([]);

        $this->assertNull($dto->email);
    }

    public function test_explicit_null_for_non_nullable_default_public_property_fails_validation(): void
    {
        $this->expectException(ValidationException::class);

        DefaultPublicPropertyFixtureDto::fromArray([
            'name' => null,
        ]);
    }

    public function test_duplicate_mapkey_definitions_are_rejected(): void
    {
        $this->expectException(InspectionException::class);

        DuplicateMappedKeyFixtureDto::fromArray([
            'shared' => 'value',
        ]);
    }

    public function test_constraint_attributes_are_enforced_as_contract(): void
    {
        $this->expectException(ValidationException::class);

        AttributeConstraintFixtureDto::fromArray([
            'age' => 17,
            'name' => 'Al',
        ]);
    }

    public function test_readonly_public_property_hydration_uses_package_exception(): void
    {
        $this->expectException(InstantiationException::class);

        ReadonlyPublicPropertyFixtureDto::fromArray([
            'name' => 'Ada',
        ]);
    }
}

final class RulesFixtureDto
{
    use AsDto;

    public function __construct(public readonly int $age) {}

    public static function rules(): array
    {
        return [
            'age' => 'min:18',
        ];
    }
}

final class DateCastFixtureDto
{
    use AsDto;

    public function __construct(public readonly Carbon $createdAt) {}
}

enum FixtureIntStatus: int
{
    case Draft = 1;
}

final class IntBackedEnumFixtureDto
{
    use AsDto;

    public function __construct(public readonly FixtureIntStatus $status) {}
}

final class NullablePublicPropertyFixtureDto
{
    use AsDto;

    public ?string $email;
}

final class DefaultPublicPropertyFixtureDto
{
    use AsDto;

    public string $name = 'default';
}

final class DuplicateMappedKeyFixtureDto
{
    use AsDto;

    #[MapKey('shared')]
    public ?string $first = null;

    #[MapKey('shared')]
    public string $second;
}

final class AttributeConstraintFixtureDto
{
    use AsDto;

    public function __construct(
        #[Min(18)]
        public readonly int $age,
        #[StringLength(min: 3, max: 10)]
        public readonly string $name,
    ) {}
}

final class ReadonlyPublicPropertyFixtureDto
{
    use AsDto;

    public readonly string $name;
}
