<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Axiom\DataForge\Core\DtoInspector;
use Axiom\DataForge\Core\Validator;
use Axiom\DataForge\Exceptions\ValidationException;
use Tests\Unit\Core\Objects\TestDto;

// Define a simple DTO class for testing

test('validator requires required fields', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, []);

    try {
        $validator->validate();
        $this->fail('ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors)->toHaveKey('name')
            ->and($e->errors)->toHaveKey('age')
            ->and($e->errors)->not->toHaveKey('email')
            ->and($e->errors)->not->toHaveKey('url');
    }
});

test('validator validates field types', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid types
    $validValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
        'email' => 'john@example.com',
    ]);
    $validData = $validValidator->validate();
    expect($validData)->toBeArray()
        ->and($validData)->toHaveKey('name')
        ->and($validData)->toHaveKey('age')
        ->and($validData)->toHaveKey('email');

    // Invalid types
    $invalidValidator = new Validator($inspector, [
        'name' => 123, // should be string
        'age' => 'thirty', // should be int
    ]);

    try {
        $invalidValidator->validate();
        $this->fail('ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors)->toHaveKey('name')
            ->and($e->errors)->toHaveKey('age');
    }
});

test('validator applies min rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Test with numeric value
    $validator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('age');
    }

    // Test with string length
    $validator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('name');
    }
});

test('validator applies max rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Test with numeric value
    $validator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('age');
    }

    // Test with string length
    $validator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('name');
    }
});

test('validator applies in rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    $validValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
    ]);

    $validValidator->withRules([
        'name' => 'in:John,Jane,Bob',
    ]);

    $validData = $validValidator->validate();
    expect($validData)->toHaveKey('name')
        ->and($validData['name'])->toBe('John');

    // Invalid value not in the list
    $invalidValidator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('name');
    }
});

test('validator applies regex rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid pattern match
    $validValidator = new Validator($inspector, [
        'name' => 'John123',
        'age' => 30,
    ]);

    $validValidator->withRules([
        'name' => 'regex:/^[A-Za-z0-9]+$/',
    ]);

    $validData = $validValidator->validate();
    expect($validData)->toHaveKey('name')
        ->and($validData['name'])->toBe('John123');

    // Invalid pattern match
    $invalidValidator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('name');
    }
});

test('validator applies email rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid email
    $validValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
        'email' => 'john@example.com',
    ]);

    $validValidator->withRules([
        'email' => 'email',
    ]);

    $validData = $validValidator->validate();
    expect($validData)->toHaveKey('email')
        ->and($validData['email'])->toBe('john@example.com');

    // Invalid email
    $invalidValidator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('email');
    }
});

test('validator applies url rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid URL
    $validValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
        'url' => 'https://example.com',
    ]);

    $validValidator->withRules([
        'url' => 'url',
    ]);

    $validData = $validValidator->validate();
    expect($validData)->toHaveKey('url')
        ->and($validData['url'])->toBe('https://example.com');

    // Invalid URL
    $invalidValidator = new Validator($inspector, [
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
        expect($e->errors)->toHaveKey('url');
    }
});

test('validator uses custom error messages', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, [
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
        expect($e->errors['name'][0])->toBe($customMessage);
    }
});

test('validateSafe returns data when validation passes', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
    ]);

    $data = $validator->validateSafe();
    expect($data)->toBeArray()
        ->and($data)->toHaveKey('name')
        ->and($data)->toHaveKey('age');
});

test('validateSafe returns false when validation fails', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, []);

    $result = $validator->validateSafe();
    expect($result)->toBeFalse();
});

test('getErrors returns validation errors', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, []);

    $result = $validator->validateSafe();
    expect($result)->toBeFalse();

    $errors = $validator->getErrors();
    expect($errors)->toBeArray()
        ->and($errors)->toHaveKey('name')
        ->and($errors)->toHaveKey('age');
});
