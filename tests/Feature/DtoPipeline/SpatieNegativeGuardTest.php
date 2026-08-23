<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Intentional non-support guards inspired by Spatie cast and name-mapper tests.
 *
 * These lock in Data Forge "never guess" contracts: scalars are validated by
 * type rather than broadly coerced, ambiguous collections are not inferred, and
 * snake/camel/studly/class name-mapper conventions are not applied implicitly.
 *
 * Spatie ports of the same upstream behavior would convert these inputs. Data
 * Forge must reject them. See docs/spatie-laravel-data-test-port-backlog.md
 * (D003 name mappers, D006 broad casts, D009/D010 collection inference).
 */
final class SpatieNegativeGuardTest extends TestCase
{
    public function test_integer_input_is_not_broadly_cast_to_string_field(): void
    {
        // Spatie BuiltinTypeCast casts 42 -> "42"; Data Forge must not.
        try {
            GuardStringFieldDto::fromArray([
                'label' => 42,
            ]);

            $this->fail('Expected integer input to fail validation for a string field.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('label', $e->errors);
        }
    }

    public function test_boolean_input_is_not_broadly_cast_to_string_field(): void
    {
        try {
            GuardStringFieldDto::fromArray([
                'label' => true,
            ]);

            $this->fail('Expected boolean input to fail validation for a string field.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('label', $e->errors);
        }
    }

    public function test_object_input_is_not_broadly_cast_to_array_field(): void
    {
        // Spatie BuiltinTypeCast turns (object) ['key' => 'value'] into an array.
        try {
            GuardArrayFieldDto::fromArray([
                'items' => (object) ['key' => 'value'],
            ]);

            $this->fail('Expected object input to fail validation for an array field.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors);
        }
    }

    public function test_collection_field_without_array_of_rejects_array_input(): void
    {
        // Without #[ArrayOf] there is no item contract, so an array of records
        // must not be coerced into a typed Collection nor inferred from shape.
        try {
            GuardPlainCollectionDto::fromArray([
                'things' => [
                    ['name' => 'Ada'],
                    ['name' => 'Grace'],
                ],
            ]);

            $this->fail('Expected array input to fail for an un-annotated Collection field.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('things', $e->errors);
        }
    }

    public function test_collection_field_without_array_of_accepts_existing_collection_instance(): void
    {
        // The caller must supply the Collection; the package will not build one.
        $dto = GuardPlainCollectionDto::fromArray([
            'things' => new Collection(['a', 'b']),
        ]);

        $this->assertInstanceOf(Collection::class, $dto->things);
        $this->assertSame(['a', 'b'], $dto->things->all());
    }

    public function test_snake_case_input_is_not_implicitly_mapped_to_camel_case_property(): void
    {
        // No #[MapKey], so snake_case is not a hidden alias for the camelCase name.
        try {
            GuardCamelCaseDto::fromArray([
                'first_name' => 'Ada',
            ]);

            $this->fail('Expected snake_case input not to satisfy a camelCase property.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('firstName', $e->errors);
            $this->assertArrayNotHasKey('first_name', $e->errors);
        }
    }

    public function test_studly_case_input_is_not_implicitly_mapped_to_camel_case_property(): void
    {
        try {
            GuardCamelCaseDto::fromArray([
                'FirstName' => 'Ada',
            ]);

            $this->fail('Expected StudlyCase input not to satisfy a camelCase property.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('firstName', $e->errors);
            $this->assertArrayNotHasKey('FirstName', $e->errors);
        }
    }

    public function test_strict_dto_rejects_snake_case_input_for_camel_case_property(): void
    {
        // Strict input surfaces the unmapped key verbatim instead of converting it.
        try {
            GuardStrictCamelCaseDto::fromArray([
                'first_name' => 'Ada',
            ]);

            $this->fail('Expected strict input to reject the snake_case key.');
        } catch (UnknownInputKeyException $e) {
            $this->assertArrayHasKey('first_name', $e->errors);
        }
    }
}

final class GuardStringFieldDto
{
    use AsDto;

    public function __construct(public readonly string $label) {}
}

final class GuardArrayFieldDto
{
    use AsDto;

    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(public readonly array $items) {}
}

final class GuardPlainCollectionDto
{
    use AsDto;

    /**
     * @param  Collection<int, mixed>  $things
     */
    public function __construct(public readonly Collection $things) {}
}

final class GuardCamelCaseDto
{
    use AsDto;

    public function __construct(public readonly string $firstName) {}
}

final class GuardStrictCamelCaseDto
{
    use AsStrictInputDto;

    public function __construct(public readonly string $firstName) {}
}
