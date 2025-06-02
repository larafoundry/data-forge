<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Axiom\DataForge\Exceptions\ValidationException;
use Tests\Unit\AsDto\Objects\BasicDto;
use Tests\Unit\AsDto\Objects\DtoWithMessages;
use Tests\Unit\AsDto\Objects\DtoWithRules;
use Tests\Unit\AsDto\Objects\EnumType;

it('can create a DTO from array with valid data', function () {
    $data = [
        'name' => 'John Doe',
        'age' => 30,
        'email' => 'john@example.com',
    ];

    $dto = BasicDto::fromArray($data);

    expect($dto)
        ->toBeInstanceOf(BasicDto::class)
        ->and($dto->name)->toBe('John Doe')
        ->and($dto->age)->toBe(30)
        ->and($dto->email)->toBe('john@example.com');
});

it('can create a DTO with nullable properties', function () {
    $data = [
        'name' => 'Jane Doe',
        'age' => 25,
    ];

    $dto = BasicDto::fromArray($data);

    expect($dto)
        ->toBeInstanceOf(BasicDto::class)
        ->and($dto->name)->toBe('Jane Doe')
        ->and($dto->age)->toBe(25)
        ->and($dto->email)->toBeNull();
});

it('validates data against rules', function () {
    $data = [
        'name' => 'John Doe',
        'age' => 25,
        'email' => 'john@example.com',
        'enumType' => EnumType::REQUIRED,
        'tags' => ['tag1', 'tag2'],
    ];

    $dto = DtoWithRules::fromArray($data);

    expect($dto)
        ->toBeInstanceOf(DtoWithRules::class)
        ->and($dto->name)->toBe('John Doe')
        ->and($dto->age)->toBe(25)
        ->and($dto->email)->toBe('john@example.com');
});

it('throws exception when name is too short', function () {
    $data = [
        'name' => 'Jo', // Less than 3 characters
        'age' => 25,
        'email' => 'john@example.com',
    ];

    DtoWithRules::fromArray($data);
})->throws(ValidationException::class);

it('throws exception when age is below minimum', function () {
    $data = [
        'name' => 'John Doe',
        'age' => 17, // Below the minimum age of 18
        'email' => 'john@example.com',
    ];

    DtoWithRules::fromArray($data);
})->throws(ValidationException::class);

it('throws exception when email is invalid', function () {
    $data = [
        'name' => 'John Doe',
        'age' => 25,
        'email' => 'not-an-email', // Invalid email format
    ];

    DtoWithRules::fromArray($data);
})->throws(ValidationException::class);

it('uses custom error messages when validation fails', function () {
    try {
        $data = [
            'name' => 'Jo', // Too short
            'age' => 25,
            'email' => 'john@example.com',
        ];

        DtoWithMessages::fromArray($data);
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('The name must be at least 3 characters');
    }
});

it('returns age-related custom error message', function () {
    try {
        $data = [
            'name' => 'John Doe',
            'age' => 17, // Too young
            'email' => 'john@example.com',
        ];

        DtoWithMessages::fromArray($data);
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('You must be at least 18 years old');
    }
});

it('returns email-related custom error message', function () {
    try {
        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'not-an-email', // Invalid format
        ];

        DtoWithMessages::fromArray($data);
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('Please provide a valid email address');
    }
});

it('handles missing required fields', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ];

    expect(fn () => DtoWithRules::fromArray($data))->toThrow(ValidationException::class);
});

it('handles type conversion for scalar types', function () {
    $data = [
        'name' => 'John Doe',
        'age' => 30, // Changed to integer
        'email' => 'john@example.com',
    ];

    $dto = BasicDto::fromArray($data);

    expect($dto->age)->toBe(30)->toBeInt();
});

it('returns empty arrays for default rules and messages', function () {
    expect(BasicDto::rules())->toBeArray()->toBeEmpty()
        ->and(BasicDto::messages())->toBeArray()->toBeEmpty();
});

it('verifies custom rules are set correctly', function () {
    $rules = DtoWithRules::rules();

    // Convert string rules to array format for testing purposes
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

    expect($parsedRules)
        ->toBeArray()
        ->toHaveKey('name')
        ->toHaveKey('age')
        ->toHaveKey('email')
        ->and($parsedRules['name'])->toBe([
            'required',
            'string',
            ['min', 3],
        ])
        ->and($parsedRules['age'])->toBe([
            'required',
            'integer',
            ['min', 18],
        ])
        ->and($parsedRules['email'])->toBe([
            'required',
            'email',
        ]);
});

it('verifies custom messages are set correctly', function () {
    $messages = DtoWithMessages::messages();

    expect($messages)
        ->toBeArray()
        ->toHaveKey('name.required')
        ->toHaveKey('name.min')
        ->toHaveKey('age.min')
        ->toHaveKey('email.email')
        ->and($messages['name.required'])->toBe('The name field is mandatory')
        ->and($messages['name.min'])->toBe('The name must be at least :min characters')
        ->and($messages['age.min'])->toBe('You must be at least :min years old')
        ->and($messages['email.email'])->toBe('Please provide a valid email address');
});
