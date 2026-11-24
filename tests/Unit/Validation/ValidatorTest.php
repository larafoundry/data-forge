<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Schema\DtoSchemaCompiler;
use Axiom\DataForge\Validation\Validator;
use Tests\TestCase;
use Tests\Unit\Validation\Objects\TestDto;

final class ValidatorTest extends TestCase
{
    public function test_validator_requires_required_fields(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);
        $validator = Validator::from($schema, []);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
            $this->assertArrayHasKey('age', $e->errors);
            $this->assertArrayNotHasKey('email', $e->errors);
            $this->assertArrayNotHasKey('url', $e->errors);
        }
    }

    public function test_validator_uses_default_laravel_validation_messages(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);
        $validator = Validator::from($schema, []);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('The name field is required.', $e->errors['name'][0]);
            $this->assertNotSame('validation.required', $e->errors['name'][0]);
        }
    }

    public function test_validator_validates_field_types(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validValidator = Validator::from($schema, [
            'name' => 'John',
            'age' => 30,
            'email' => 'john@example.com',
        ]);
        $validData = $validValidator->validate();

        $this->assertIsArray($validData);
        $this->assertArrayHasKey('name', $validData);
        $this->assertArrayHasKey('age', $validData);
        $this->assertArrayHasKey('email', $validData);

        $invalidValidator = Validator::from($schema, [
            'name' => 123,
            'age' => 'thirty',
        ]);

        try {
            $invalidValidator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
            $this->assertArrayHasKey('age', $e->errors);
        }
    }

    public function test_validator_applies_min_rule_correctly(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validator = Validator::from($schema, [
            'name' => 'John',
            'age' => 15,
        ]);

        $validator->withRules([
            'age' => 'min:18',
        ]);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('age', $e->errors);
        }

        $validator = Validator::from($schema, [
            'name' => 'Jo',
            'age' => 25,
        ]);

        $validator->withRules([
            'name' => ['min:3'],
        ]);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_validator_applies_max_rule_correctly(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validator = Validator::from($schema, [
            'name' => 'John',
            'age' => 65,
        ]);

        $validator->withRules([
            'age' => 'numeric|max:60',
        ]);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('age', $e->errors);
        }

        $validator = Validator::from($schema, [
            'name' => 'John Doe Smith',
            'age' => 25,
        ]);

        $validator->withRules([
            'name' => 'max:10',
        ]);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_validator_applies_in_rule_correctly(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validValidator = Validator::from($schema, [
            'name' => 'John',
            'age' => 30,
        ]);

        $validValidator->withRules([
            'name' => 'in:John,Jane,Bob',
        ]);

        $validData = $validValidator->validate();
        $this->assertArrayHasKey('name', $validData);
        $this->assertSame('John', $validData['name']);

        $invalidValidator = Validator::from($schema, [
            'name' => 'Alice',
            'age' => 30,
        ]);

        $invalidValidator->withRules([
            'name' => 'in:John,Jane,Bob',
        ]);

        try {
            $invalidValidator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_validator_applies_regex_rule_correctly(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validValidator = Validator::from($schema, [
            'name' => 'John123',
            'age' => 30,
        ]);

        $validValidator->withRules([
            'name' => 'regex:/^[A-Za-z0-9]+$/',
        ]);

        $validData = $validValidator->validate();
        $this->assertArrayHasKey('name', $validData);
        $this->assertSame('John123', $validData['name']);

        $invalidValidator = Validator::from($schema, [
            'name' => 'John@123',
            'age' => 30,
        ]);

        $invalidValidator->withRules([
            'name' => 'regex:/^[A-Za-z0-9]+$/',
        ]);

        try {
            $invalidValidator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_validator_applies_email_rule_correctly(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validValidator = Validator::from($schema, [
            'name' => 'John',
            'age' => 30,
            'email' => 'john@example.com',
        ]);

        $validValidator->withRules([
            'email' => 'email',
        ]);

        $validData = $validValidator->validate();
        $this->assertArrayHasKey('email', $validData);
        $this->assertSame('john@example.com', $validData['email']);

        $invalidValidator = Validator::from($schema, [
            'name' => 'John',
            'age' => 30,
            'email' => 'not-an-email',
        ]);

        $invalidValidator->withRules([
            'email' => 'email',
        ]);

        try {
            $invalidValidator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors);
        }
    }

    public function test_validator_applies_url_rule_correctly(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);

        $validValidator = Validator::from($schema, [
            'name' => 'John',
            'age' => 30,
            'url' => 'https://example.com',
        ]);

        $validValidator->withRules([
            'url' => 'url',
        ]);

        $validData = $validValidator->validate();
        $this->assertArrayHasKey('url', $validData);
        $this->assertSame('https://example.com', $validData['url']);

        $invalidValidator = Validator::from($schema, [
            'name' => 'John',
            'age' => 30,
            'url' => 'not-a-url',
        ]);

        $invalidValidator->withRules([
            'url' => 'url',
        ]);

        try {
            $invalidValidator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('url', $e->errors);
        }
    }

    public function test_validator_uses_custom_error_messages(): void
    {
        $schema = DtoSchemaCompiler::compile(TestDto::class);
        $validator = Validator::from($schema, [
            'age' => 15,
        ]);

        $customMessage = 'Name is a required field';
        $validator->withMessages([
            'name.required' => $customMessage,
        ]);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame($customMessage, $e->errors['name'][0]);
        }
    }
}
