<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use Closure;
use Illuminate\Contracts\Validation\Rule as LegacyValidationRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\TestCase;
use Stringable;

final class LaravelRuleObjectTest extends TestCase
{
    public function test_rule_in_object_validates_input(): void
    {
        $dto = LaravelRuleInDto::fromArray([
            'status' => 'draft',
        ]);

        $this->assertSame('draft', $dto->status);

        try {
            LaravelRuleInDto::fromArray([
                'status' => 'archived',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors);
        }
    }

    public function test_direct_rule_object_is_supported_as_a_field_rule(): void
    {
        $dto = LaravelDirectNotInRuleDto::fromArray([
            'status' => 'draft',
        ]);

        $this->assertSame('draft', $dto->status);

        try {
            LaravelDirectNotInRuleDto::fromArray([
                'status' => 'archived',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors);
        }
    }

    public function test_rule_enum_object_validates_then_hydrates_enum(): void
    {
        $dto = LaravelRuleEnumDto::fromArray([
            'state' => 'published',
        ]);

        $this->assertSame(LaravelRuleObjectState::Published, $dto->state);

        try {
            LaravelRuleEnumDto::fromArray([
                'state' => 'missing',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('state', $e->errors);
        }
    }

    public function test_modern_validation_rule_contract_object_is_supported(): void
    {
        $dto = LaravelContractRuleDto::fromArray([
            'name' => 'Ada',
        ]);

        $this->assertSame('Ada', $dto->name);

        try {
            LaravelContractRuleDto::fromArray([
                'name' => 'Bob',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['The name must start with A.'], $e->errors['name']);
        }
    }

    public function test_legacy_validation_rule_contract_object_is_supported_for_laravel_compatibility(): void
    {
        $dto = LaravelLegacyContractRuleDto::fromArray([
            'name' => 'Ada',
        ]);

        $this->assertSame('Ada', $dto->name);

        try {
            LaravelLegacyContractRuleDto::fromArray([
                'name' => 'Bob',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['The name must start with A.'], $e->errors['name']);
        }
    }

    public function test_rule_objects_work_on_nested_collection_paths(): void
    {
        try {
            LaravelRuleCollectionParentDto::fromArray([
                'items' => [
                    ['status' => 'active'],
                    ['status' => 'inactive'],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.1.status', $e->errors);
        }
    }

    public function test_closure_rules_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Closure validation rules are unsupported.');

        DtoRuleBuilder::normalizeFieldRules([
            static function (string $attribute, mixed $value, Closure $fail): void {},
        ]);
    }

    public function test_nested_rule_arrays_are_rejected(): void
    {
        $this->expectException(InspectionException::class);

        DtoRuleBuilder::normalizeFieldRules(['required', ['min', 3]]);
    }

    public function test_database_backed_rule_objects_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Database-backed validation rules');

        DtoRuleBuilder::normalizeFieldRules([Rule::exists('users', 'id')]);
    }

    public function test_unique_rule_object_is_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Database-backed validation rules');

        DtoRuleBuilder::normalizeFieldRules([Rule::unique('users', 'email')]);
    }

    public function test_non_laravel_stringable_rule_objects_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('supported stringable rule builders');

        LaravelStringableValueObjectRuleDto::fromArray([
            'price' => '10.00',
        ]);
    }

    public function test_database_backed_string_rule_arrays_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Database-backed validation rules');

        DtoRuleBuilder::normalizeFieldRules(['required', 'exists:users,id']);
    }

    public function test_database_backed_pipe_string_rules_are_rejected(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Database-backed validation rules');

        DtoRuleBuilder::normalizeFieldRules('required|unique:users,email');
    }
}

enum LaravelRuleObjectState: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class LaravelRuleInDto
{
    use AsDto;

    public function __construct(public readonly string $status) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }
}

final class LaravelDirectNotInRuleDto
{
    use AsDto;

    public function __construct(public readonly string $status) {}

    /**
     * @return array<string, object>
     */
    public static function rules(): array
    {
        return [
            'status' => Rule::notIn(['archived']),
        ];
    }
}

final class LaravelRuleEnumDto
{
    use AsDto;

    public function __construct(public readonly LaravelRuleObjectState $state) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(LaravelRuleObjectState::class)],
        ];
    }
}

final class LaravelContractRuleDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', new StartsWithAValidationRule()],
        ];
    }
}

final class StartsWithAValidationRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, 'A')) {
            $fail('The :attribute must start with A.');
        }
    }
}

final class LaravelLegacyContractRuleDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', new StartsWithALegacyRule()],
        ];
    }
}

final class StartsWithALegacyRule implements LegacyValidationRule
{
    public function passes($attribute, $value): bool
    {
        return is_string($value) && str_starts_with($value, 'A');
    }

    public function message(): string
    {
        return 'The :attribute must start with A.';
    }
}

final class LaravelRuleCollectionParentDto
{
    use AsDto;

    /**
     * @param  Collection<int, LaravelRuleCollectionItemDto>  $items
     */
    public function __construct(
        #[ArrayOf(LaravelRuleCollectionItemDto::class)]
        public readonly Collection $items,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.status' => [Rule::in(['active'])],
        ];
    }
}

final class LaravelRuleCollectionItemDto
{
    use AsDto;

    public function __construct(public readonly string $status) {}
}

final class LaravelStringableValueObjectRuleDto
{
    use AsDto;

    public function __construct(public readonly string $price) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'price' => ['required', new LaravelStringableMoneyValueObject()],
        ];
    }
}

final class LaravelStringableMoneyValueObject implements Stringable
{
    public function __toString(): string
    {
        return '10.00';
    }
}
