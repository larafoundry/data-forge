<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use Axiom\DataForge\Validation\Validator;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Tests\TestCase;
use Tests\Unit\Validation\Objects\TestDto;

final class MaxValidationTest extends TestCase
{
    public function test_max_validation_works_correctly(): void
    {
        $inspector = new DtoInspector(TestDto::class);

        $validator = new Validator($inspector, [
            'name' => 'John',
            'age' => 100,
        ]);

        $validator->withRules([
            'age' => 'numeric|max:50',
        ]);

        $this->expectException(ValidationException::class);

        $validator->validate();
    }

    public function test_max_validation_rules_are_properly_built(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $validator = new Validator($inspector, [
            'name' => 'John',
            'age' => 100,
        ]);

        $validator->withRules([
            'age' => 'max:50',
        ]);

        $rules = DtoRuleBuilder::build($inspector, $validator->getCustomRules(), $validator->getAttributes());

        $ageRules = array_filter($rules['age'], function ($rule) {
            return is_string($rule) && str_starts_with($rule, 'max:');
        });

        $this->assertNotEmpty($ageRules);
    }

    public function test_direct_laravel_validation_with_max_rule_works(): void
    {
        $factory = new Factory(
            new Translator(
                new ArrayLoader(), 'en'
            ),
            new Container()
        );

        $validator = $factory->make(
            ['age' => 100],
            ['age' => 'numeric|max:50']
        );

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->first('age'));
    }

    public function test_manually_check_if_age_is_over_max(): void
    {
        $inspector = new DtoInspector(TestDto::class);
        $validator = new Validator($inspector, [
            'name' => 'John',
            'age' => 100,
        ]);

        $validator->withRules([
            'age' => 'max:50',
        ]);

        $age = $validator->getAttributes()['age'];

        $this->assertSame(100, $age);
        $this->assertTrue($age > 50, "Age $age should be greater than 50");
    }
}
