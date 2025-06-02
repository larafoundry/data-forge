<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit;

use Axiom\DataForge\Core\ContainerHelper;
use InvalidArgumentException;
use Tests\Unit\ContainerHelper\Objects\Address;
use Tests\Unit\ContainerHelper\Objects\ImmutablePoint;
use Tests\Unit\ContainerHelper\Objects\PrivateConstructObject;
use Tests\Unit\ContainerHelper\Objects\Product;
use Tests\Unit\ContainerHelper\Objects\ProtectedConstructObject;
use Tests\Unit\ContainerHelper\Objects\User;
use TypeError;

test('can create instance with constructor parameters', function () {
    $class = new class('', 0)
    {
        public function __construct(
            public string $name,
            public int $age
        ) {}
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
        'age' => 30,
    ]);

    expect($instance->name)->toBe('John')
        ->and($instance->age)->toBe(30);
});

test('can create instance with public properties', function () {
    $class = new class
    {
        public string $name;

        public int $age;
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
        'age' => 30,
    ]);

    expect($instance->name)->toBe('John')
        ->and($instance->age)->toBe(30);
});

test('throws exception for non-existent class', function () {
    expect(fn () => ContainerHelper::makeInstance('NonExistentClass', []))
        ->toThrow(InvalidArgumentException::class);
});

test('throws exception for missing required constructor parameter', function () {
    $class = new class('', 0)
    {
        public function __construct(
            public string $name,
            public int $age
        ) {}
    };

    expect(fn () => ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
    ]))->toThrow(InvalidArgumentException::class);
});

test('uses default values for optional constructor parameters', function () {
    $class = new class('')
    {
        public function __construct(
            public string $name,
            public int $age = 25
        ) {}
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
    ]);

    expect($instance->name)->toBe('John')
        ->and($instance->age)->toBe(25);
});

test('ignores non-public properties', function () {
    $class = new class
    {
        public string $public;

        protected int $age;

        /** @noinspection PhpUnusedPrivateFieldInspection */
        private string $name;
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
        'age' => 30,
        'public' => 'value',
    ]);

    expect($instance->public)->toBe('value');
});

test('can create User instance with constructor parameters', function () {
    $instance = ContainerHelper::makeInstance(User::class, [
        'name' => 'John Doe',
        'age' => 30,
    ]);

    expect($instance->name)->toBe('John Doe')
        ->and($instance->age)->toBe(30);
});

test('can create ImmutablePoint instance with readonly properties', function () {
    $instance = ContainerHelper::makeInstance(ImmutablePoint::class, [
        'x' => 10,
        'y' => 20,
    ]);

    expect($instance->x)->toBe(10)
        ->and($instance->y)->toBe(20);
});

test('can create Product instance with public property', function () {
    $instance = ContainerHelper::makeInstance(Product::class, [
        'name' => 'Laptop',
    ]);

    expect($instance->name)->toBe('Laptop');
});

test('can create Address instance with multiple public properties', function () {
    $instance = ContainerHelper::makeInstance(Address::class, [
        'street' => '123 Main St',
        'city' => 'New York',
    ]);

    expect($instance->street)->toBe('123 Main St')
        ->and($instance->city)->toBe('New York');
});

test('throws exception when creating User with missing required parameters', function () {
    expect(fn () => ContainerHelper::makeInstance(User::class, [
        'name' => 'John Doe',
    ]))->toThrow(InvalidArgumentException::class);
});

test('throws exception when creating ImmutablePoint with invalid parameters', function () {
    expect(fn () => ContainerHelper::makeInstance(ImmutablePoint::class, [
        'x' => 'invalid',
        'y' => 20,
    ]))->toThrow(TypeError::class);
});

test('can create object when creating instance with private constructor', function () {
    $object = ContainerHelper::makeInstance(PrivateConstructObject::class, [
        'value' => 'test',
    ]);
    expect($object->getValue())->toBe('test');
});

test('can create object when creating instance with protected constructor', function () {
    $object = ContainerHelper::makeInstance(ProtectedConstructObject::class, [
        'value' => 'test',
    ]);
    expect($object->getValue())->toBe('test');
});
