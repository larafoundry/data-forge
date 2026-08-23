<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use PHPUnit\Framework\TestCase;

final class SpatieRulePortTest extends TestCase
{
    public function test_pipe_string_rule_normalizes_concatenated_rules(): void
    {
        $rules = DtoRuleBuilder::normalizeFieldRules('required|min:0');

        $this->assertSame(['required', 'min:0'], $rules);
    }

    public function test_array_rule_with_regex_pipe_is_preserved_as_single_rule(): void
    {
        $rules = DtoRuleBuilder::normalizeFieldRules(['required', 'string', 'regex:/test|ok/']);

        $this->assertSame(['required', 'string', 'regex:/test|ok/'], $rules);
    }

    public function test_static_pipe_string_rule_combination_validates_input(): void
    {
        $dto = SpatiePipeStringRuleDto::fromArray([
            'quantity' => 3,
        ]);

        $this->assertSame(3, $dto->quantity);

        try {
            SpatiePipeStringRuleDto::fromArray([
                'quantity' => 1,
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors);
        }
    }

    public function test_static_array_rule_with_regex_pipe_validates_successful_input(): void
    {
        $dto = SpatieRegexRuleDto::fromArray([
            'property' => 'ok',
        ]);

        $this->assertSame('ok', $dto->property);
    }

    public function test_static_array_rule_with_regex_pipe_rejects_invalid_input(): void
    {
        try {
            SpatieRegexRuleDto::fromArray([
                'property' => 'nope',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('property', $e->errors);
        }
    }
}

final class SpatiePipeStringRuleDto
{
    use AsDto;

    public function __construct(public readonly int $quantity) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:2',
        ];
    }
}

final class SpatieRegexRuleDto
{
    use AsDto;

    public function __construct(public readonly string $property) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'property' => ['string', 'required', 'regex:/test|ok/'],
        ];
    }
}
