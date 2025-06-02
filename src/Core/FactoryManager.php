<?php

declare(strict_types=1);

namespace Axiom\DataForge\Core;

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
     * @param  DtoInspector<T>  $inspector
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

            // Check if it's a class property
            $isProperty = $reflection->hasProperty($property);

            // Check if it's a constructor parameter
            $isConstructorParam = false;
            $constructor = $reflection->getConstructor();
            if ($constructor !== null) {
                foreach ($constructor->getParameters() as $param) {
                    if ($param->getName() === $property) {
                        $isConstructorParam = true;
                        break;
                    }
                }
            }

            // Throw exception only if it's neither a property nor a constructor parameter
            if (! $isProperty && ! $isConstructorParam) {
                throw new InvalidArgumentException("'$property' does not exist as a property or constructor parameter on $this->class");
            }
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Failed to validate property: {$e->getMessage()}", 0, $e);
        }
    }
}
