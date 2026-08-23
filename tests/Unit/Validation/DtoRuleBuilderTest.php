<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use Closure;
use Tests\TestCase;
use Tests\Unit\Validation\Objects\TestDto;

final class DtoRuleBuilderTest extends TestCase
{
    public function test_normalize_field_rules_handles_pipe_string_correctly(): void
    {
        $rules = DtoRuleBuilder::normalizeFieldRules('required|min:3|max:10');

        $this->assertIsArray($rules);
        $this->assertCount(3, $rules);
        $this->assertContains('required', $rules);
        $this->assertContains('min:3', $rules);
        $this->assertContains('max:10', $rules);
    }

    public function test_normalize_field_rules_handles_array_of_strings_correctly(): void
    {
        $rules = DtoRuleBuilder::normalizeFieldRules(['required', 'min:3', 'max:10']);

        $this->assertIsArray($rules);
        $this->assertCount(3, $rules);
        $this->assertContains('required', $rules);
        $this->assertContains('min:3', $rules);
        $this->assertContains('max:10', $rules);
    }

    public function test_normalize_field_rules_throws_exception_for_invalid_array_items(): void
    {
        $this->expectException(InspectionException::class);

        DtoRuleBuilder::normalizeFieldRules(['required', ['min', 3], 'max:10']);
    }

    public function test_build_creates_required_rules(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $rules = DtoRuleBuilder::build($inspector, [], []);

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('age', $rules);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['age']);
    }

    public function test_build_handles_optional_properties(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $rules = DtoRuleBuilder::build($inspector, [], []);

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('url', $rules);
        $this->assertGreaterThanOrEqual(1, count($rules['email']));
        $this->assertGreaterThanOrEqual(1, count($rules['url']));
    }

    public function test_build_merges_custom_rules(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $customRules = [
            'name' => ['min:3', 'max:50'],
            'email' => ['email'],
        ];

        $rules = DtoRuleBuilder::build($inspector, $customRules, []);

        $this->assertIsArray($rules);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('min:3', $rules['name']);
        $this->assertContains('max:50', $rules['name']);
        $this->assertContains('email', $rules['email']);
    }

    public function test_build_adds_nullable_rule_for_unknown_payload_keys(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $payload = [
            'name' => 'John',
            'age' => 30,
            'unknown_field' => 'some value',
        ];

        $rules = DtoRuleBuilder::build($inspector, [], $payload);

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('unknown_field', $rules);
        $this->assertContains('nullable', $rules['unknown_field']);
    }

    public function test_build_adds_type_validation(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $rules = DtoRuleBuilder::build($inspector, [], []);

        $this->assertIsArray($rules);
        $this->assertGreaterThanOrEqual(1, count($rules['name']));
        $this->assertGreaterThanOrEqual(1, count($rules['age']));

        $nameHasClosure = false;
        $ageHasClosure = false;

        foreach ($rules['name'] as $rule) {
            if ($rule instanceof Closure) {
                $nameHasClosure = true;
                break;
            }
        }

        foreach ($rules['age'] as $rule) {
            if ($rule instanceof Closure) {
                $ageHasClosure = true;
                break;
            }
        }

        $this->assertTrue($nameHasClosure);
        $this->assertTrue($ageHasClosure);
    }
}
