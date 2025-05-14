<?php

namespace Ws\DataBridge\Core;

use Faker\Factory;
use Faker\Generator;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use DateTimeInterface;
use DateTimeImmutable;
use BackedEnum;
use ReflectionEnum;
use ReflectionClass;
use RuntimeException;

/**
 * Generates random values for supported PHP property types.
 */
final class RandomDataGenerator
{
    public function __construct(private readonly Generator $faker)
    {
    }

    /**
     * Create a new instance with default Faker generator
     */
    public static function create(): self
    {
        return new self(Factory::create());
    }

    /**
     * Generate a random value based on the given type
     *
     * @param ReflectionType $type
     * @param string|null $propertyName The name of the property (if available)
     * @return mixed
     */
    public function generate(mixed $type, ?string $propertyName = null): mixed
    {
        if ($type instanceof ReflectionNamedType) {
            return $this->generateForNamedType($type, $propertyName);
        }

        if ($type instanceof ReflectionUnionType) {
            // For union types, pick one of the types randomly
            $types = $type->getTypes();
            $randomType = $types[array_rand($types)];
            return $this->generate($randomType, $propertyName);
        }

        if ($type instanceof ReflectionIntersectionType) {
            // Intersection types are complex, we'll throw an exception for now
            throw new RuntimeException("Cannot generate random data for intersection types");
        }

        throw new RuntimeException("Unsupported reflection type: " . get_class($type));
    }

    /**
     * Generate a random value for a named type
     *
     * @param ReflectionNamedType $type
     * @param string|null $propertyName The name of the property (if available)
     * @return mixed
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
            DateTimeInterface::class, \DateTime::class, DateTimeImmutable::class => new DateTimeImmutable(),
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
     * Handle complex types like enums and objects
     *
     * @param string $typeName
     * @return mixed
     */
    private function handleComplexType(string $typeName): mixed
    {
        // Check if it's an enum
        if (class_exists($typeName) && enum_exists($typeName)) {
            return $this->generateEnum($typeName);
        }

        // Check if it's a class that can be instantiated
        if (class_exists($typeName)) {
            return $this->generateObject($typeName);
        }

        // Default fallback
        return $this->faker->word();
    }

    /**
     * Generate a random enum value
     *
     * @param class-string<BackedEnum> $enumClass
     * @return BackedEnum
     */
    private function generateEnum(string $enumClass): BackedEnum
    {
        $reflectionEnum = new ReflectionEnum($enumClass);

        if (!$reflectionEnum->isBacked()) {
            throw new RuntimeException("Only backed enums are supported for random generation");
        }

        $cases = $reflectionEnum->getCases();

        if (empty($cases)) {
            throw new RuntimeException("Enum {$enumClass} has no cases");
        }

        return $cases[array_rand($cases)]->getValue();
    }

    /**
     * Generate a random object
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function generateObject(string $className): object
    {
        // Use FactoryManager to create the object
        return FactoryManager::from($className)
            ->fillRandom()
            ->make();
    }
}
