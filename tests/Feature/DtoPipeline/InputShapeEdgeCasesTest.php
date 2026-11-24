<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Attributes\DateFormat;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class InputShapeEdgeCasesTest extends TestCase
{
    public function test_list_given_for_a_nested_dto_is_rejected(): void
    {
        $this->assertValidationErrors(
            ['child' => ['The child field must be a DTO record.']],
            fn () => EdgeParentFixtureDto::fromArray(['child' => ['Ada', 'Lovelace']])
        );
    }

    public function test_list_given_for_a_nested_dto_is_rejected_in_strict_mode(): void
    {
        $this->assertValidationErrors(
            ['child' => ['The child field must be a DTO record.']],
            fn () => EdgeStrictParentFixtureDto::fromArray(['child' => ['Ada', 'Lovelace']])
        );
    }

    public function test_list_given_for_a_strict_collection_item_is_rejected(): void
    {
        $this->assertValidationErrors(
            ['items.0' => ['The items.0 field must be a DTO record.']],
            fn () => EdgeStrictListFixtureDto::fromArray(['items' => [['Ada']]])
        );
    }

    public function test_scalar_collection_item_is_rejected(): void
    {
        $this->assertValidationErrors(
            ['items.1' => ['The items.1 field has an invalid type.']],
            fn () => EdgeListFixtureDto::fromArray(['items' => [['name' => 'Ada'], 'Grace']])
        );
    }

    public function test_pure_enum_instance_is_accepted(): void
    {
        $dto = EdgePureEnumFixtureDto::fromArray(['status' => EdgePureStatus::Active]);

        $this->assertSame(EdgePureStatus::Active, $dto->status);
    }

    public function test_date_format_with_an_escaped_pipe_still_resets_missing_fields(): void
    {
        $dto = EdgeEscapedPipeDateFormatFixtureDto::fromArray(['date' => '2024-12-31 |']);

        $this->assertSame('2024-12-31 00:00:00.000000', $dto->date->format('Y-m-d H:i:s.u'));
    }

    public function test_constructor_parameter_without_a_property_is_accepted(): void
    {
        $dto = EdgeComputedFixtureDto::fromArray(['name' => 'ada', 'id' => 7]);

        $this->assertSame('ADA', $dto->label());
        $this->assertSame(7, $dto->id);
    }

    public function test_instantiated_nested_dto_with_a_constructor_only_parameter_is_trusted(): void
    {
        $child = EdgeComputedFixtureDto::fromArray(['name' => 'ada', 'id' => 7]);

        $dto = EdgeComputedParentFixtureDto::fromArray(['child' => $child]);

        $this->assertSame($child, $dto->child);
    }

    public function test_static_property_does_not_shadow_a_constructor_parameter(): void
    {
        $schema = DtoSchemaCompiler::compile(EdgeStaticShadowFixtureDto::class);
        $dto = EdgeStaticShadowFixtureDto::fromArray(['name' => 'Ada']);

        $this->assertSame(['name'], array_keys($schema->fields));
        $this->assertNull($schema->field('name')?->property);
        $this->assertSame('Ada', $dto->stored());
    }

    public function test_wildcard_rule_on_a_builtin_array_field_is_applied(): void
    {
        $this->assertValidationErrors(
            ['tags.1' => ['The tags.1 field must be a string.']],
            fn () => EdgeRulePathsFixtureDto::fromArray([
                'tags' => ['php', 42],
                'labels' => new Collection(['beta']),
                'items' => [['name' => 'Ada']],
            ])
        );
    }

    public function test_wildcard_rule_on_a_non_dto_object_field_is_applied(): void
    {
        $this->assertValidationErrors(
            ['labels.0' => ['The labels.0 field must be a string.']],
            fn () => EdgeRulePathsFixtureDto::fromArray([
                'tags' => ['php'],
                'labels' => new Collection([42]),
                'items' => [['name' => 'Ada']],
            ])
        );
    }

    public function test_wildcard_rule_on_a_dto_collection_is_applied(): void
    {
        $this->assertValidationErrors(
            ['items.0' => ['The items.0 field must have at least 1 items.']],
            fn () => EdgeRulePathsFixtureDto::fromArray([
                'tags' => ['php'],
                'labels' => new Collection(['beta']),
                'items' => [[]],
            ])
        );
    }

    public function test_numeric_size_rule_next_to_a_rule_object_uses_numeric_semantics(): void
    {
        $dto = EdgeNumericRulesFixtureDto::fromArray(['age' => 120, 'score' => 7]);

        $this->assertSame(120, $dto->age);

        $this->assertValidationErrors(
            ['age' => ['The age field must be at least 18.']],
            fn () => EdgeNumericRulesFixtureDto::fromArray(['age' => 9, 'score' => 7])
        );
    }

    public function test_numeric_field_without_size_rules_keeps_its_rules(): void
    {
        $this->assertValidationErrors(
            ['score' => ['The selected score is invalid.']],
            fn () => EdgeNumericRulesFixtureDto::fromArray(['age' => 20, 'score' => 13])
        );
    }

    public function test_child_rules_and_parent_dotted_rules_for_the_same_field_both_apply(): void
    {
        $this->assertSame('Ada', EdgeRuleMergeParentFixtureDto::fromArray(['child' => ['name' => 'Ada']])->child->name);

        $this->assertValidationErrors(
            ['child.name' => ['The name field must be at least 3 characters.']],
            fn () => EdgeRuleMergeParentFixtureDto::fromArray(['child' => ['name' => 'Al']])
        );

        $this->assertValidationErrors(
            ['child.name' => ['The child.name field must not be greater than 5 characters.']],
            fn () => EdgeRuleMergeParentFixtureDto::fromArray(['child' => ['name' => 'Grace Hopper']])
        );
    }

    public function test_parent_rules_reach_items_of_a_nested_collection(): void
    {
        $dto = EdgeDeepCollectionFixtureDto::fromArray(['list' => ['items' => [['name' => 'Ada']]]]);

        $this->assertSame('Ada', $dto->list->items->first()?->name);

        $this->assertValidationErrors(
            ['list.items.1.name' => ['The list.items.1.name field must not be greater than 4 characters.']],
            fn () => EdgeDeepCollectionFixtureDto::fromArray([
                'list' => ['items' => [['name' => 'Ada'], ['name' => 'Grace']]],
            ])
        );
    }

    public function test_rule_for_a_single_collection_item_index_is_applied(): void
    {
        $dto = EdgeIndexedItemRuleFixtureDto::fromArray(['items' => [['name' => 'Ada']]]);

        $this->assertSame('Ada', $dto->items->first()?->name);
    }

    /**
     * @param  array<string, array<int, string>>  $expected
     */
    private function assertValidationErrors(array $expected, Closure $build): void
    {
        try {
            $build();

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame($expected, $e->errors);
        }
    }
}

final class EdgeChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class EdgeParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly EdgeChildFixtureDto $child) {}
}

final class EdgeStrictChildFixtureDto
{
    use AsStrictInputDto;

    public function __construct(public readonly string $name) {}
}

final class EdgeStrictParentFixtureDto
{
    use AsStrictInputDto;

    public function __construct(public readonly EdgeStrictChildFixtureDto $child) {}
}

final class EdgeStrictListFixtureDto
{
    use AsStrictInputDto;

    /**
     * @param  Collection<int, EdgeStrictChildFixtureDto>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}
}

final class EdgeListFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, EdgeChildFixtureDto>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}
}

enum EdgePureStatus
{
    case Active;
    case Archived;
}

final class EdgePureEnumFixtureDto
{
    use AsDto;

    public function __construct(public readonly EdgePureStatus $status) {}
}

final class EdgeEscapedPipeDateFormatFixtureDto
{
    use AsDto;

    public function __construct(
        #[DateFormat('Y-m-d \\|')]
        public readonly CarbonImmutable $date,
    ) {}
}

final class EdgeComputedFixtureDto
{
    use AsDto;

    private string $label;

    public function __construct(string $name, public readonly int $id)
    {
        $this->label = strtoupper($name);
    }

    public function label(): string
    {
        return $this->label;
    }
}

final class EdgeComputedParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly EdgeComputedFixtureDto $child) {}
}

final class EdgeStaticShadowFixtureDto
{
    use AsDto;

    public static string $name = 'static';

    private string $stored;

    public function __construct(string $name)
    {
        $this->stored = $name;
    }

    public function stored(): string
    {
        return $this->stored;
    }
}

final class EdgeRulePathsFixtureDto
{
    use AsDto;

    /**
     * @param  array<int, mixed>  $tags
     * @param  Collection<int, mixed>  $labels
     * @param  Collection<int, EdgeChildFixtureDto>  $items
     */
    public function __construct(
        public readonly array $tags,
        public readonly Collection $labels,
        public readonly Collection $items,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'tags.*' => 'string',
            'labels.*' => 'string',
            'items.*' => 'array|min:1',
        ];
    }
}

final class EdgeAlwaysPassesRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void {}
}

final class EdgeNumericRulesFixtureDto
{
    use AsDto;

    public function __construct(
        public readonly int $age,
        public readonly int $score,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'age' => [new EdgeAlwaysPassesRule(), 'min:18'],
            'score' => 'not_in:13',
        ];
    }
}

final class EdgeRuleMergeChildFixtureDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return ['name' => 'min:3'];
    }
}

final class EdgeRuleMergeParentFixtureDto
{
    use AsDto;

    public function __construct(public readonly EdgeRuleMergeChildFixtureDto $child) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return ['child.name' => 'max:5'];
    }
}

final class EdgeNestedListFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, EdgeChildFixtureDto>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}
}

final class EdgeDeepCollectionFixtureDto
{
    use AsDto;

    public function __construct(public readonly EdgeNestedListFixtureDto $list) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return ['list.items.*.name' => 'max:4'];
    }
}

final class EdgeIndexedItemRuleFixtureDto
{
    use AsDto;

    /**
     * @param  Collection<int, EdgeChildFixtureDto>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return ['items.0' => 'array'];
    }
}
