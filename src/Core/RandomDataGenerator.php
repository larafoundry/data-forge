<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Faker\Factory;
use Faker\Generator;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * Generates random values for supported PHP property types.
 */
final class RandomDataGenerator
{
    public function __construct(private readonly Generator $faker) {}

    public static function create(): self
    {
        return new self(Factory::create());
    }

    /**
     * @param  ReflectionType  $type
     *
     * @throws Throwable
     */
    public function generate(mixed $type, ?string $propertyName = null): mixed
    {
        if ($type instanceof ReflectionNamedType) {
            return $this->generateForNamedType($type, $propertyName);
        }

        if ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();
            $randomType = $types[array_rand($types)];

            return $this->generate($randomType, $propertyName);
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw new RuntimeException('Cannot generate random data for intersection types');
        }

        throw new RuntimeException('Unsupported reflection type: '.get_class($type));
    }

    /**
     * @param  string|null  $propertyName  The name of the property (if available)
     *
     * @throws Throwable
     */
    private function generateForNamedType(ReflectionNamedType $type, ?string $propertyName = null): mixed
    {
        $typeName = $type->getName();

        // Handle nullable types
        if ($type->allowsNull() && $this->faker->boolean(20)) { // 20% chance of null
            return null;
        }

        // Special handling for common property names
        if ($typeName === 'string' && $propertyName !== null) {
            if ($propertyName === 'email') {
                return $this->faker->safeEmail();
            }
            if ($propertyName === 'name' || $propertyName === 'fullName') {
                return $this->faker->name();
            }
            if ($propertyName === 'firstName') {
                return $this->faker->firstName();
            }
            if ($propertyName === 'lastName') {
                return $this->faker->lastName();
            }
            if ($propertyName === 'address') {
                return $this->faker->address();
            }
            if ($propertyName === 'phone' || $propertyName === 'phoneNumber') {
                return $this->faker->phoneNumber();
            }
        }

        // Handle built-in types
        return match ($typeName) {
            'int' => $this->faker->numberBetween(1, 1000),
            'float' => $this->faker->randomFloat(),
            'string' => $this->faker->word(),
            'bool', 'boolean' => $this->faker->boolean(),
            'array' => $this->generateArray(),
            DateTimeInterface::class, DateTime::class, DateTimeImmutable::class => new DateTimeImmutable(),
            default => $this->handleComplexType($typeName),
        };
    }

    /**
     * Generate a random array
     *
     * @return array<int, mixed>
     */
    private function generateArray(): array
    {
        $count = $this->faker->numberBetween(0, 5);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->faker->word();
        }

        return $result;
    }

    /**
     * @throws Throwable
     */
    private function handleComplexType(string $typeName): mixed
    {
        if (class_exists($typeName) && enum_exists($typeName)) {
            return $this->generateEnum($typeName);
        }

        if (class_exists($typeName)) {
            return $this->generateObject($typeName);
        }

        return $this->faker->word();
    }

    /**
     * @template T of BackedEnum|UnitEnum
     * @param  class-string<T>  $enumClass
     * @return T
     *
     * @throws ReflectionException
     */
    private function generateEnum(string $enumClass)
    {
        $reflectionEnum = new ReflectionEnum($enumClass);
        $cases = $reflectionEnum->getCases();

        if (empty($cases)) {
            throw new RuntimeException("Enum $enumClass has no cases");
        }

        $case = $cases[array_rand($cases)];

        /** @var T */
        return $enumClass::{$case->getName()};
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $className
     * @return T
     *
     * @throws Throwable
     */
    private function generateObject(string $className)
    {
        return FactoryManager::from($className)
            ->fillRandom()
            ->make();
    }
}
