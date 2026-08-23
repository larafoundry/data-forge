<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Hydration;

use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Hydration\ContainerHelper;
use Tests\TestCase;
use Tests\Unit\Hydration\Objects\Address;
use Tests\Unit\Hydration\Objects\ImmutablePoint;
use Tests\Unit\Hydration\Objects\PrivateConstructObject;
use Tests\Unit\Hydration\Objects\Product;
use Tests\Unit\Hydration\Objects\ProtectedConstructObject;
use Tests\Unit\Hydration\Objects\User;

final class ContainerHelperTest extends TestCase
{
    public function test_can_create_instance_with_constructor_parameters(): void
    {
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

        $this->assertSame('John', $instance->name);
        $this->assertSame(30, $instance->age);
    }

    public function test_can_create_instance_with_public_properties(): void
    {
        $class = new class
        {
            public string $name;

            public int $age;
        };

        $instance = ContainerHelper::makeInstance(get_class($class), [
            'name' => 'John',
            'age' => 30,
        ]);

        $this->assertSame('John', $instance->name);
        $this->assertSame(30, $instance->age);
    }

    public function test_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(InstantiationException::class);

        ContainerHelper::makeInstance('NonExistentClass', []);
    }

    public function test_throws_exception_for_missing_required_constructor_parameter(): void
    {
        $this->expectException(InstantiationException::class);

        $class = new class('', 0)
        {
            public function __construct(
                public string $name,
                public int $age
            ) {}
        };

        ContainerHelper::makeInstance(get_class($class), [
            'name' => 'John',
        ]);
    }

    public function test_uses_default_values_for_optional_constructor_parameters(): void
    {
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

        $this->assertSame('John', $instance->name);
        $this->assertSame(25, $instance->age);
    }

    public function test_ignores_non_public_properties(): void
    {
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

        $this->assertSame('value', $instance->public);
    }

    public function test_can_create_user_instance_with_constructor_parameters(): void
    {
        $instance = ContainerHelper::makeInstance(User::class, [
            'name' => 'John Doe',
            'age' => 30,
        ]);

        $this->assertSame('John Doe', $instance->name);
        $this->assertSame(30, $instance->age);
    }

    public function test_can_create_immutable_point_instance_with_readonly_properties(): void
    {
        $instance = ContainerHelper::makeInstance(ImmutablePoint::class, [
            'x' => 10,
            'y' => 20,
        ]);

        $this->assertSame(10, $instance->x);
        $this->assertSame(20, $instance->y);
    }

    public function test_can_create_product_instance_with_public_property(): void
    {
        $instance = ContainerHelper::makeInstance(Product::class, [
            'name' => 'Laptop',
        ]);

        $this->assertSame('Laptop', $instance->name);
    }

    public function test_can_create_address_instance_with_multiple_public_properties(): void
    {
        $instance = ContainerHelper::makeInstance(Address::class, [
            'street' => '123 Main St',
            'city' => 'New York',
        ]);

        $this->assertSame('123 Main St', $instance->street);
        $this->assertSame('New York', $instance->city);
    }

    public function test_throws_exception_when_creating_user_with_missing_required_parameters(): void
    {
        $this->expectException(InstantiationException::class);

        ContainerHelper::makeInstance(User::class, [
            'name' => 'John Doe',
        ]);
    }

    public function test_throws_exception_when_creating_immutable_point_with_invalid_parameters(): void
    {
        $this->expectException(InstantiationException::class);

        ContainerHelper::makeInstance(ImmutablePoint::class, [
            'x' => 'invalid',
            'y' => 20,
        ]);
    }

    public function test_can_create_object_when_creating_instance_with_private_constructor(): void
    {
        $object = ContainerHelper::makeInstance(PrivateConstructObject::class, [
            'value' => 'test',
        ]);

        $this->assertSame('test', $object->getValue());
    }

    public function test_can_create_object_when_creating_instance_with_protected_constructor(): void
    {
        $object = ContainerHelper::makeInstance(ProtectedConstructObject::class, [
            'value' => 'test',
        ]);

        $this->assertSame('test', $object->getValue());
    }
}
