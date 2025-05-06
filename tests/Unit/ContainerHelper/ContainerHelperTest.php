<?php

namespace Tests\Unit;

use Ws\DataBridge\Helpers\ContainerHelper;
use InvalidArgumentException;
use ReflectionException;

test('can create instance with constructor parameters', function () {
    $class = new class('', 0) {
        public function __construct(
            public string $name,
            public int $age
        ) {}
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
        'age' => 30
    ]);

    expect($instance->name)->toBe('John')
        ->and($instance->age)->toBe(30);
});

test('can create instance with public properties', function () {
    $class = new class {
        public string $name;
        public int $age;
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
        'age' => 30
    ]);

    expect($instance->name)->toBe('John')
        ->and($instance->age)->toBe(30);
});

test('throws exception for non-existent class', function () {
    expect(fn() => ContainerHelper::makeInstance('NonExistentClass', []))
        ->toThrow(\InvalidArgumentException::class);
});

test('throws exception for missing required constructor parameter', function () {
    $class = new class('', 0) {
        public function __construct(
            public string $name,
            public int $age
        ) {}
    };

    expect(fn() => ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John'
    ]))->toThrow(\InvalidArgumentException::class);
});

test('uses default values for optional constructor parameters', function () {
    $class = new class('') {
        public function __construct(
            public string $name,
            public int $age = 25
        ) {}
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John'
    ]);

    expect($instance->name)->toBe('John')
        ->and($instance->age)->toBe(25);
});

test('ignores non-public properties', function () {
    $class = new class {
        private string $name;
        protected int $age;
        public string $public;
    };

    $instance = ContainerHelper::makeInstance(get_class($class), [
        'name' => 'John',
        'age' => 30,
        'public' => 'value'
    ]);

    expect($instance->public)->toBe('value');
}); 