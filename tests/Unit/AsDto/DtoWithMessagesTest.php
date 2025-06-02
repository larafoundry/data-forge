<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Axiom\DataForge\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\AsDto\Objects\DtoWithMessages;

class DtoWithMessagesTest extends TestCase
{
    public function test_can_create_dto_with_valid_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'john@example.com',
        ];

        $dto = DtoWithMessages::fromArray($data);

        $this->assertInstanceOf(DtoWithMessages::class, $dto);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(25, $dto->age);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_shows_custom_message_when_name_too_short(): void
    {
        try {
            $data = [
                'name' => 'Jo',  // Less than 3 characters
                'age' => 25,
                'email' => 'john@example.com',
            ];

            DtoWithMessages::fromArray($data);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('The name must be at least 3 characters', $e->getMessage());
        }
    }

    public function test_shows_custom_message_when_age_too_young(): void
    {
        try {
            $data = [
                'name' => 'John Doe',
                'age' => 17,  // Less than 18
                'email' => 'john@example.com',
            ];

            DtoWithMessages::fromArray($data);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('You must be at least 18 years old', $e->getMessage());
        }
    }

    public function test_shows_custom_message_when_email_invalid(): void
    {
        try {
            $data = [
                'name' => 'John Doe',
                'age' => 25,
                'email' => 'not-an-email',  // Invalid email
            ];

            DtoWithMessages::fromArray($data);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Please provide a valid email address', $e->getMessage());
        }
    }

    public function test_messages_method_returns_expected_messages(): void
    {
        $messages = DtoWithMessages::messages();

        $this->assertIsArray($messages);
        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.min', $messages);
        $this->assertArrayHasKey('age.min', $messages);
        $this->assertArrayHasKey('email.email', $messages);

        $this->assertEquals('The name field is mandatory', $messages['name.required']);
        $this->assertEquals('The name must be at least :min characters', $messages['name.min']);
        $this->assertEquals('You must be at least :min years old', $messages['age.min']);
        $this->assertEquals('Please provide a valid email address', $messages['email.email']);
    }
}
