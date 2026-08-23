<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Hydration\ContainerHelper;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class DefaultsAndConfigurationTest extends TestCase
{
    public function test_promoted_constructor_default_is_not_required_input(): void
    {
        $dto = PromotedDefaultFixtureDto::fromArray([]);

        $this->assertSame('guest', $dto->name);
    }

    public function test_date_format_attribute_accepts_valid_raw_date_before_hydration(): void
    {
        $dto = DateFormatCarbonFixtureDto::fromArray([
            'date' => '2024-01-02',
        ]);

        $this->assertSame('2024-01-02', $dto->date->format('Y-m-d'));
    }

    public function test_optional_mapkey_property_uses_default_when_mapped_key_is_missing(): void
    {
        $dtoWithOriginalAlias = OptionalMapKeyAliasFixtureDto::fromArray([
            'nickname' => 'Ada',
        ]);
        $dto = OptionalMapKeyAliasFixtureDto::fromArray([]);

        $this->assertNull($dtoWithOriginalAlias->nickname);
        $this->assertNull($dto->nickname);
    }

    public function test_container_helper_wraps_constructor_type_errors(): void
    {
        $this->expectException(InstantiationException::class);

        ContainerHelper::makeInstance(ConstructorTypeErrorFixtureDto::class, [
            'age' => 'not-an-int',
        ]);
    }

    public function test_invalid_static_rule_shape_uses_inspection_exception(): void
    {
        $this->expectException(InspectionException::class);

        InvalidRuleShapeFixtureDto::fromArray([
            'name' => 'Ada',
        ]);
    }
}

final class PromotedDefaultFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name = 'guest') {}
}

final class DateFormatCarbonFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d')]
        public readonly Carbon $date,
    ) {}
}

final class OptionalMapKeyAliasFixtureDto
{
    use AsDto;

    public function __construct(
        #[MapKey('nick_name')]
        public readonly ?string $nickname = null,
    ) {}
}

final class ConstructorTypeErrorFixtureDto
{
    public function __construct(public readonly int $age) {}
}

final class InvalidRuleShapeFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    public static function rules(): array
    {
        return [
            0 => 'required',
        ];
    }
}
