<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Ws\DataBridge\Exceptions\ValidationException;

/**
 * @template T of object
 *
 * @psalm-type Override = array<string,mixed>
 */
final class FactoryManager
{
    /** @var array<string, mixed> */
    private array $values = [];

    private bool $shouldFillRandom = false;

    /**
     * @param  class-string<T>  $class
     */
    private function __construct(private readonly string $class) {}

    /**
     * @template U of object
     *
     * @param  class-string<U>  $class
     * @return FactoryManager<U>
     */
    public static function from(string $class): self
    {
        return new self($class);
    }

    /**
     * Set a value for a property
     *
     * @return $this
     *
     * @throws InvalidArgumentException If the property doesn't exist on the DTO
     */
    public function with(string $property, mixed $value): self
    {
        $this->validatePropertyExists($property);
        $this->values[$property] = $value;

        return $this;
    }

    /**
     * Set multiple values at once
     *
     * @param  array<string, mixed>  $values
     * @return $this
     *
     * @throws InvalidArgumentException If any property doesn't exist on the DTO
     */
    public function withValues(array $values): self
    {
        foreach ($values as $property => $value) {
            $this->with($property, $value);
        }

        return $this;
    }

    /**
     * Auto-fill every missing property with random data
     *
     * @return $this
     */
    public function fillRandom(): self
    {
        $this->shouldFillRandom = true;

        return $this;
    }

    /**
     * Create a new instance with the configured values
     *
     * @return T
     *
     * @throws ReflectionException|ValidationException
     */
    public function make(): object
    {
        $inspector = new DtoInspector($this->class);
        $data = $this->values;

        // If random fill is enabled, populate missing properties
        if ($this->shouldFillRandom) {
            $data = $this->fillMissingProperties($inspector, $data);
        }

        $validator = Validator::from($inspector, $data);
        $validatedData = $validator->validate();

        return ContainerHelper::makeInstance($this->class, $validatedData);
    }

    /**
     * Fill missing properties with random data
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     */
    private function fillMissingProperties(DtoInspector $inspector, array $data): array
    {
        $generator = RandomDataGenerator::create();
        $reflection = new ReflectionClass($this->class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();

            // Skip if property already has a value
            if (array_key_exists($name, $data)) {
                continue;
            }

            // Get the property type
            $type = $property->getType();
            if ($type === null) {
                continue; // Skip properties without type hints
            }

            // Generate random value based on type and property name
            $data[$name] = $generator->generate($type, $name);
        }

        return $data;
    }

    /**
     * Validate that a property exists on the DTO
     *
     * @throws InvalidArgumentException
     */
    private function validatePropertyExists(string $property): void
    {
        try {
            $reflection = new ReflectionClass($this->class);
            if (! $reflection->hasProperty($property)) {
                throw new InvalidArgumentException("Property '{$property}' does not exist on {$this->class}");
            }
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Failed to validate property: {$e->getMessage()}", 0, $e);
        }
    }
}
