<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto;

use Tests\Unit\AsDto\Objects\BasicDto;
use Tests\Unit\AsDto\Objects\DtoWithRules;
use Tests\Unit\AsDto\Objects\DtoWithMessages;
use Ws\DataBridge\Exceptions\ValidationException;

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
        'age' => 17, // Below minimum age of 18
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
    
    expect($rules)
        ->toBeArray()
        ->toHaveKey('name')
        ->toHaveKey('age')
        ->toHaveKey('email')
        ->and($rules['name'])->toBe([
            'required', 
            'string', 
            ['min', 3]
        ])
        ->and($rules['age'])->toBe([
            'required', 
            'integer', 
            ['min', 18]
        ])
        ->and($rules['email'])->toBe([
            'required', 
            'email'
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
