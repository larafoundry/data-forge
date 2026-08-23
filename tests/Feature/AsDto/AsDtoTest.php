<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature\AsDto;

use Axiom\DataForge\Exceptions\ValidationException;
use Tests\Feature\AsDto\Objects\BasicDto;
use Tests\Feature\AsDto\Objects\DtoWithMessages;
use Tests\Feature\AsDto\Objects\DtoWithRules;
use Tests\Feature\AsDto\Objects\EnumType;
use Tests\TestCase;

final class AsDtoTest extends TestCase
{
    public function test_can_create_a_dto_from_array_with_valid_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
        ];

        $dto = BasicDto::fromArray($data);

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertSame('John Doe', $dto->name);
        $this->assertSame(30, $dto->age);
        $this->assertSame('john@example.com', $dto->email);
    }

    public function test_can_create_a_dto_with_nullable_properties(): void
    {
        $data = [
            'name' => 'Jane Doe',
            'age' => 25,
        ];

        $dto = BasicDto::fromArray($data);

        $this->assertInstanceOf(BasicDto::class, $dto);
        $this->assertSame('Jane Doe', $dto->name);
        $this->assertSame(25, $dto->age);
        $this->assertNull($dto->email);
    }

    public function test_validates_data_against_rules(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'john@example.com',
            'enumType' => EnumType::REQUIRED,
            'tags' => ['tag1', 'tag2'],
        ];

        $dto = DtoWithRules::fromArray($data);

        $this->assertInstanceOf(DtoWithRules::class, $dto);
        $this->assertSame('John Doe', $dto->name);
        $this->assertSame(25, $dto->age);
        $this->assertSame('john@example.com', $dto->email);
    }

    public function test_throws_exception_when_name_is_too_short(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'Jo', // Too short
            'age' => 25,
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_throws_exception_when_age_is_below_minimum(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'age' => 17, // Below the minimum age of 18
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_throws_exception_when_email_is_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'not-an-email', // Invalid email format
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_uses_custom_error_messages_when_validation_fails(): void
    {
        try {
            $data = [
                'name' => 'Jo', // Too short
                'age' => 25,
                'email' => 'john@example.com',
            ];

            DtoWithMessages::fromArray($data);
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('The name must be at least 3 characters', $e->getMessage());
        }
    }

    public function test_returns_age_related_custom_error_message(): void
    {
        try {
            $data = [
                'name' => 'John Doe',
                'age' => 17, // Too young
                'email' => 'john@example.com',
            ];

            DtoWithMessages::fromArray($data);
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('You must be at least 18 years old', $e->getMessage());
        }
    }

    public function test_returns_email_related_custom_error_message(): void
    {
        try {
            $data = [
                'name' => 'John Doe',
                'age' => 25,
                'email' => 'not-an-email', // Invalid format
            ];

            DtoWithMessages::fromArray($data);
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Please provide a valid email address', $e->getMessage());
        }
    }

    public function test_handles_missing_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        DtoWithRules::fromArray($data);
    }

    public function test_handles_type_conversion_for_scalar_types(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30, // Changed to integer
            'email' => 'john@example.com',
        ];

        $dto = BasicDto::fromArray($data);

        $this->assertSame(30, $dto->age);
        $this->assertIsInt($dto->age);
    }

    public function test_returns_empty_arrays_for_default_rules_and_messages(): void
    {
        $this->assertSame([], BasicDto::rules());
        $this->assertSame([], BasicDto::messages());
    }

    public function test_verifies_custom_rules_are_set_correctly(): void
    {
        $rules = DtoWithRules::rules();

        $parsedRules = [];
        foreach ($rules as $field => $rule) {
            if (is_string($rule)) {
                $segments = explode('|', $rule);
                $parsed = [];
                foreach ($segments as $segment) {
                    if (str_contains($segment, ':')) {
                        [$ruleName, $param] = explode(':', $segment, 2);
                        if (is_numeric($param)) {
                            $param = (int) $param;
                        }
                        $parsed[] = [$ruleName, $param];
                    } else {
                        $parsed[] = $segment;
                    }
                }
                $parsedRules[$field] = $parsed;
            } else {
                $parsedRules[$field] = $rule;
            }
        }

        $this->assertIsArray($parsedRules);
        $this->assertArrayHasKey('name', $parsedRules);
        $this->assertArrayHasKey('age', $parsedRules);
        $this->assertArrayHasKey('email', $parsedRules);
        $this->assertSame([
            'required',
            'string',
            ['min', 3],
        ], $parsedRules['name']);
        $this->assertSame([
            'required',
            'integer',
            ['min', 18],
        ], $parsedRules['age']);
        $this->assertSame([
            'required',
            'email',
        ], $parsedRules['email']);
    }

    public function test_verifies_custom_messages_are_set_correctly(): void
    {
        $messages = DtoWithMessages::messages();

        $this->assertIsArray($messages);
        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.min', $messages);
        $this->assertArrayHasKey('age.min', $messages);
        $this->assertArrayHasKey('email.email', $messages);
        $this->assertSame('The name field is mandatory', $messages['name.required']);
        $this->assertSame('The name must be at least :min characters', $messages['name.min']);
        $this->assertSame('You must be at least :min years old', $messages['age.min']);
        $this->assertSame('Please provide a valid email address', $messages['email.email']);
    }
}
