<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Tests\Unit\Core\Objects\TestDto;
use Ws\DataBridge\Core\DtoInspector;
use Ws\DataBridge\Core\Validator;

// Define a simple DTO class for testing

test('validator requires required fields', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, []);

    $errors = $validator->validate();

    expect($errors)->toHaveKey('name')
        ->and($errors)->toHaveKey('age')
        ->and($errors)->not->toHaveKey('email')
        ->and($errors)->not->toHaveKey('url');
});

test('validator validates field types', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid types
    $validValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
        'email' => 'john@example.com',
    ]);
    $validErrors = $validValidator->validate();
    expect($validErrors)->toBeEmpty();

    // Invalid types
    $invalidValidator = new Validator($inspector, [
        'name' => 123, // should be string
        'age' => 'thirty', // should be int
    ]);
    $invalidErrors = $invalidValidator->validate();
    expect($invalidErrors)->toHaveKey('name')
        ->and($invalidErrors)->toHaveKey('age');
});

test('validator applies min rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Test with numeric value
    $validator = new Validator($inspector, [
        'name' => 'John',
        'age' => 15,
    ]);

    $validator->withRules([
        'age' => ['min' => 18],
    ]);

    $errors = $validator->validate();
    expect($errors)->toHaveKey('age');

    // Test with string length
    $validator = new Validator($inspector, [
        'name' => 'Jo',
        'age' => 25,
    ]);

    $validator->withRules([
        'name' => ['min' => 3],
    ]);

    $errors = $validator->validate();
    expect($errors)->toHaveKey('name');
});

test('validator applies max rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Test with numeric value
    $validator = new Validator($inspector, [
        'name' => 'John',
        'age' => 65,
    ]);

    $validator->withRules([
        'age' => ['max' => 60],
    ]);

    $errors = $validator->validate();
    expect($errors)->toHaveKey('age');

    // Test with string length
    $validator = new Validator($inspector, [
        'name' => 'John Doe Smith',
        'age' => 25,
    ]);

    $validator->withRules([
        'name' => ['max' => 10],
    ]);

    $errors = $validator->validate();
    expect($errors)->toHaveKey('name');
});

test('validator applies in rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid value in the list
    $validValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
    ]);

    $validValidator->withRules([
        'name' => ['in' => ['John', 'Jane', 'Bob']],
    ]);

    $validErrors = $validValidator->validate();
    expect($validErrors)->not->toHaveKey('name');

    // Invalid value not in the list
    $invalidValidator = new Validator($inspector, [
        'name' => 'Alice',
        'age' => 30,
    ]);

    $invalidValidator->withRules([
        'name' => ['in' => ['John', 'Jane', 'Bob']],
    ]);

    $invalidErrors = $invalidValidator->validate();
    expect($invalidErrors)->toHaveKey('name');
});

test('validator applies regex rule correctly', function () {
    $inspector = new DtoInspector(TestDto::class);

    // Valid pattern match
    $validValidator = new Validator($inspector, [
        'name' => 'John123',
        'age' => 30,
    ]);

    $validValidator->withRules([
        'name' => ['regex' => '/^[A-Za-z0-9]+$/'],
    ]);

    $validErrors = $validValidator->validate();
    expect($validErrors)->not->toHaveKey('name');

    // Invalid pattern match
    $invalidValidator = new Validator($inspector, [
        'name' => 'John@123',
        'age' => 30,
    ]);

    $invalidValidator->withRules([
        'name' => ['regex' => '/^[A-Za-z0-9]+$/'],
    ]);

    $invalidErrors = $invalidValidator->validate();
    expect($invalidErrors)->toHaveKey('name');
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
        'email' => ['email' => true],
    ]);

    $validErrors = $validValidator->validate();
    expect($validErrors)->not->toHaveKey('email');

    // Invalid email
    $invalidValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
        'email' => 'not-an-email',
    ]);

    $invalidValidator->withRules([
        'email' => ['email' => true],
    ]);

    $invalidErrors = $invalidValidator->validate();
    expect($invalidErrors)->toHaveKey('email');
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
        'url' => ['url' => true],
    ]);

    $validErrors = $validValidator->validate();
    expect($validErrors)->not->toHaveKey('url');

    // Invalid URL
    $invalidValidator = new Validator($inspector, [
        'name' => 'John',
        'age' => 30,
        'url' => 'not-a-url',
    ]);

    $invalidValidator->withRules([
        'url' => ['url' => true],
    ]);

    $invalidErrors = $invalidValidator->validate();
    expect($invalidErrors)->toHaveKey('url');
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

    $errors = $validator->validate();
    expect($errors['name'][0])->toBe($customMessage);
});
