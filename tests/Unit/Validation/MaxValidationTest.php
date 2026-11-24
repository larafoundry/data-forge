<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
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
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validator = Validator::from($schema, [
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
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $rules = DtoRuleBuilder::build($schema, ['age' => ['max:50']], ['name' => 'John', 'age' => 100]);

        $this->assertContains('max:50', $rules['age']);
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
}
