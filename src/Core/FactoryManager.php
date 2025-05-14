<?php

namespace Ws\DataBridge\Core;

use ReflectionException;
use Ws\DataBridge\Exceptions\ValidationException;

/**
 * @template T of object
 */
final class FactoryManager
{
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @param class-string<T> $class
     */
    private function __construct(private readonly string $class)
    {
    }

    /**
     * @template U of object
     * @param class-string<U> $class
     * @return FactoryManager<U>
     */
    public static function from(string $class): self
    {
        return new self($class);
    }

    /**
     * Set a value for a property
     *
     * @param string $property
     * @param mixed $value
     * @return $this
     */
    public function with(string $property, mixed $value): self
    {
        $this->values[$property] = $value;
        return $this;
    }

    /**
     * Set multiple values at once
     *
     * @param array<string, mixed> $values
     * @return $this
     */
    public function withValues(array $values): self
    {
        foreach ($values as $property => $value) {
            $this->with($property, $value);
        }
        return $this;
    }

    /**
     * Create a new instance with the configured values
     *
     * @return T
     * @throws ReflectionException|ValidationException
     */
    public function make(): object
    {
        $inspector = new DtoInspector($this->class);
        $validator = Validator::from($inspector, $this->values);
        $validatedData = $validator->validate();
        return ContainerHelper::makeInstance($this->class, $validatedData);
    }
}
