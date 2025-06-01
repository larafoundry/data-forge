<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Throwable;

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
     * @return $this
     */
    public function fillRandom(): self
    {
        $this->shouldFillRandom = true;

        return $this;
    }

    /**
     * @return T
     *
     * @throws Throwable
     */
    public function make()
    {
        $inspector = new DtoInspector($this->class);
        $data = $this->values;

        if ($this->shouldFillRandom) {
            $data = $this->fillMissingProperties($inspector, $data);
        }

        $validator = Validator::from($inspector, $data);
        $validatedData = $validator->validate();

        return ContainerHelper::makeInstance($this->class, $validatedData);
    }

    /**
     * @return T
     *
     * @throws Throwable
     * @throws ReflectionException
     */
    public function random()
    {
        $inspector = new DtoInspector($this->class);
        $data = $this->fillMissingProperties($inspector, []);
        $validator = Validator::from($inspector, $data);
        $validatedData = $validator->validate();

        return ContainerHelper::makeInstance($this->class, $validatedData);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    private function fillMissingProperties(DtoInspector $inspector, array $data): array
    {
        $generator = RandomDataGenerator::create();
        $requiredKeys = $inspector->getRequiredKeys();

        foreach ($requiredKeys as $requiredKey) {
            if (array_key_exists($requiredKey, $data)) {
                continue;
            }
            $type = $inspector->getTypeForKey($requiredKey);
            if ($type === null) {
                continue;
            }
            $data[$requiredKey] = $generator->generate($type, $requiredKey);
        }

        return $data;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validatePropertyExists(string $property): void
    {
        try {
            $reflection = new ReflectionClass($this->class);
            if (! $reflection->hasProperty($property)) {
                throw new InvalidArgumentException("Property '$property' does not exist on $this->class");
            }
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Failed to validate property: {$e->getMessage()}", 0, $e);
        }
    }
}
