<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class NestedRulesAndInputMappingTest extends TestCase
{
    public function test_nested_numeric_min_rule_uses_nested_property_numeric_semantics(): void
    {
        $dto = NestedNumericMinParentFixtureDto::fromArray([
            'child' => [
                'age' => 1000,
            ],
        ]);

        $this->assertSame(1000, $dto->child->age);
    }

    public function test_nested_numeric_max_rule_rejects_values_above_max(): void
    {
        $this->expectException(ValidationException::class);

        NestedNumericMaxParentFixtureDto::fromArray([
            'child' => [
                'age' => 1000,
            ],
        ]);
    }

    public function test_nested_custom_date_format_rule_drives_nested_date_casting(): void
    {
        $dto = NestedDateFormatParentFixtureDto::fromArray([
            'child' => [
                'date' => '31/12/2024',
            ],
        ]);

        $this->assertSame('2024-12-31', $dto->child->date->format('Y-m-d'));
    }

    public function test_non_strict_from_array_ignores_unknown_payload_keys(): void
    {
        $dto = KnownKeysFixtureDto::fromArray([
            'name' => 'Ada',
            'extra' => 'ignored for backward compatibility',
        ]);

        $this->assertSame('Ada', $dto->name);
        $this->assertFalse(property_exists($dto, 'extra'));
    }

    public function test_strict_input_dto_rejects_unknown_payload_keys(): void
    {
        try {
            StrictKnownKeysFixtureDto::fromArray([
                'name' => 'Ada',
                'extra' => 'strict rejects this',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('extra', $e->errors);
        }
    }

    public function test_app_composed_strict_trait_rejects_unknown_payload_keys(): void
    {
        try {
            AppStrictKnownKeysFixtureDto::fromArray([
                'name' => 'Ada',
                'extra' => 'strict rejects this',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('extra', $e->errors);
        }
    }

    public function test_mapkey_reads_mapped_external_key(): void
    {
        $dto = OptionalMappedSourceFixtureDto::fromArray([
            'nick_name' => 'Ada',
        ]);

        $this->assertSame('Ada', $dto->nickname);
    }

    public function test_mapkey_original_property_key_is_ignored_in_non_strict_mode(): void
    {
        $dto = OptionalMappedSourceFixtureDto::fromArray([
            'nickname' => 'ignored because nick_name is the source',
        ]);

        $this->assertNull($dto->nickname);
    }

    public function test_mapkey_original_property_key_is_rejected_in_strict_mode(): void
    {
        try {
            StrictOptionalMappedSourceFixtureDto::fromArray([
                'nickname' => 'unknown external key',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nickname', $e->errors);
        }
    }
}

final class KnownKeysFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class StrictKnownKeysFixtureDto
{
    use AsStrictInputDto;

    public function __construct(public readonly string $name) {}
}

trait AsFixtureAppDto
{
    use AsStrictInputDto;
}

final class AppStrictKnownKeysFixtureDto
{
    use AsFixtureAppDto;

    public function __construct(public readonly string $name) {}
}

final class OptionalMappedSourceFixtureDto
{
    use AsDto;

    public function __construct(
        #[MapKey('nick_name')]
        public readonly ?string $nickname = null,
    ) {}
}

final class StrictOptionalMappedSourceFixtureDto
{
    use AsStrictInputDto;

    public function __construct(
        #[MapKey('nick_name')]
        public readonly ?string $nickname = null,
    ) {}
}

final class NestedNumericChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly int $age) {}
}

final class NestedNumericMinParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly NestedNumericChildFixtureDto $child) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'child.age' => 'min:100',
        ];
    }
}

final class NestedNumericMaxParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly NestedNumericChildFixtureDto $child) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'child.age' => 'max:50',
        ];
    }
}

final class NestedDateFormatChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly Carbon $date) {}
}

final class NestedDateFormatParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly NestedDateFormatChildFixtureDto $child) {}

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'child.date' => 'date_format:d/m/Y',
        ];
    }
}
