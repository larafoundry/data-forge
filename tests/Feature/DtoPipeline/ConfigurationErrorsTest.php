<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Attributes\StringLength;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use Carbon\Carbon;
use Error;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Mistakes in a DTO declaration are configuration problems for the developer,
 * not validation errors for the caller, so they surface as InspectionException.
 */
final class ConfigurationErrorsTest extends TestCase
{
    public function test_unknown_class_cannot_be_compiled(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Class Tests\Feature\DtoPipeline\MissingFixtureDto not found');

        DtoSchemaCompiler::compile('Tests\Feature\DtoPipeline\MissingFixtureDto');
    }

    public function test_negative_string_length_minimum_is_rejected(): void
    {
        $this->assertAttributeRuleFailure(
            fn () => NegativeStringLengthFixtureDto::fromArray(['name' => 'Ada']),
            'Min value cannot be less than 0'
        );
    }

    public function test_string_length_maximum_below_minimum_is_rejected(): void
    {
        $this->assertAttributeRuleFailure(
            fn () => InvertedStringLengthFixtureDto::fromArray(['name' => 'Ada']),
            'Max value cannot be less than min value'
        );
    }

    public function test_repeated_non_repeatable_attribute_is_rejected(): void
    {
        try {
            RepeatedDateFormatFixtureDto::fromArray(['date' => '2024-12-31']);

            $this->fail('Inspection should have failed');
        } catch (InspectionException $e) {
            $this->assertSame(
                'Failed to inspect attribute '.DateFormat::class." for field 'date' on ".RepeatedDateFormatFixtureDto::class,
                $e->getMessage()
            );
            $this->assertInstanceOf(Error::class, $e->getPrevious());
        }
    }

    public function test_rules_for_an_unknown_root_field_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage(
            "Cannot add validation rules for field 'nickname.first' because root field 'nickname' does not exist in the DTO."
        );

        UnknownRuleFieldFixtureDto::fromArray(['name' => 'Ada']);
    }

    public function test_rules_with_an_unsupported_value_type_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage(
            "Validation rules for field 'name' must be a string, supported rule object, or flat rule array"
        );

        IntegerRuleFixtureDto::fromArray(['name' => 'Ada']);
    }

    public function test_messages_with_non_string_values_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Validation messages must be string-keyed strings on '.IntegerMessageFixtureDto::class);

        IntegerMessageFixtureDto::fromArray(['name' => 'Ada']);
    }

    public function test_rules_for_a_numeric_key_inside_collection_items_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Validation rule field names must be strings.');

        NumericItemKeyRuleFixtureDto::fromArray(['items' => [['name' => 'Ada']]]);
    }

    private function assertAttributeRuleFailure(callable $build, string $reason): void
    {
        try {
            $build();

            $this->fail('Inspection should have failed');
        } catch (InspectionException $e) {
            $this->assertSame("Failed to build validation rules from attributes for field 'name'", $e->getMessage());
            $this->assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
            $this->assertSame($reason, $e->getPrevious()->getMessage());
        }
    }
}

final class ConfigurationItemFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class NegativeStringLengthFixtureDto
{
    use AsDto;

    public function __construct(
        #[StringLength(min: -1)]
        public readonly string $name,
    ) {}
}

final class InvertedStringLengthFixtureDto
{
    use AsDto;

    public function __construct(
        #[StringLength(min: 5, max: 2)]
        public readonly string $name,
    ) {}
}

final class RepeatedDateFormatFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d')]
        #[DateFormat('d/m/Y')]
        public readonly Carbon $date,
    ) {}
}

final class UnknownRuleFieldFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return ['nickname.first' => 'min:2'];
    }
}

final class IntegerRuleFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, int>
     */
    public static function rules(): array
    {
        return ['name' => 3];
    }
}

final class IntegerMessageFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, int>
     */
    public static function messages(): array
    {
        return ['name.required' => 3];
    }
}

final class NumericItemKeyRuleFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, ConfigurationItemFixtureDto>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return ['items.*.0' => 'nullable'];
    }
}
