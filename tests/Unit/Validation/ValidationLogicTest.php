<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Attributes\Max;
use Axiom\DataForge\Attributes\Min;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class ValidationLogicTest extends TestCase
{
    public function test_numeric_union_min_rule_uses_numeric_semantics(): void
    {
        $dto = UnionNumericStaticMinRuleFixtureDto::fromArray([
            'amount' => 1000,
        ]);

        $this->assertSame(1000, $dto->amount);
    }

    public function test_numeric_union_max_rule_rejects_values_above_max(): void
    {
        $this->expectException(ValidationException::class);

        UnionNumericStaticMaxRuleFixtureDto::fromArray([
            'amount' => 1000,
        ]);
    }

    public function test_numeric_union_min_attribute_uses_numeric_semantics(): void
    {
        $dto = UnionNumericAttributeMinFixtureDto::fromArray([
            'amount' => 1000,
        ]);

        $this->assertSame(1000, $dto->amount);
    }

    public function test_numeric_union_max_attribute_rejects_values_above_max(): void
    {
        $this->expectException(ValidationException::class);

        UnionNumericAttributeMaxFixtureDto::fromArray([
            'amount' => 1000,
        ]);
    }

    public function test_backed_enum_instance_with_in_rule_uses_backing_value(): void
    {
        $dto = EnumInstanceWithInRuleFixtureDto::fromArray([
            'status' => ValidationLogicFixtureStatus::Required,
        ]);

        $this->assertSame(ValidationLogicFixtureStatus::Required, $dto->status);
    }

    public function test_custom_date_format_rule_drives_date_casting(): void
    {
        $dto = RuleDateFormatCarbonFixtureDto::fromArray([
            'date' => '31/12/2024',
        ]);

        $this->assertSame('2024-12-31', $dto->date->format('Y-m-d'));
    }
}

final class UnionNumericStaticMinRuleFixtureDto
{
    use AsDto;

    public function __construct(public readonly int|float $amount) {}

    public static function rules(): array
    {
        return [
            'amount' => 'min:100',
        ];
    }
}

final class UnionNumericStaticMaxRuleFixtureDto
{
    use AsDto;

    public function __construct(public readonly int|float $amount) {}

    public static function rules(): array
    {
        return [
            'amount' => 'max:50',
        ];
    }
}

final class UnionNumericAttributeMinFixtureDto
{
    use AsDto;

    public function __construct(
        #[Min(100)]
        public readonly int|float $amount,
    ) {}
}

final class UnionNumericAttributeMaxFixtureDto
{
    use AsDto;

    public function __construct(
        #[Max(50)]
        public readonly int|float $amount,
    ) {}
}

enum ValidationLogicFixtureStatus: string
{
    case Required = 'required';
    case Optional = 'optional';
}

final class EnumInstanceWithInRuleFixtureDto
{
    use AsDto;

    public function __construct(public readonly ValidationLogicFixtureStatus $status) {}

    public static function rules(): array
    {
        return [
            'status' => 'in:required,optional',
        ];
    }
}

final class RuleDateFormatCarbonFixtureDto
{
    use AsDto;

    public function __construct(public readonly Carbon $date) {}

    public static function rules(): array
    {
        return [
            'date' => 'date_format:d/m/Y',
        ];
    }
}
