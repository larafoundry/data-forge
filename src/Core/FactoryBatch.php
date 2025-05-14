<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use InvalidArgumentException;
use ReflectionException;
use Ws\DataBridge\Exceptions\ValidationException;

/**
 * Factory for multiple objects of the same type.
 *
 * @template T of object
 */
final class FactoryBatch
{
    private int $batchCount = 1;

    private bool $shouldFillRandom = false;

    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @param  class-string<T>  $class
     */
    private function __construct(private readonly string $class) {}

    /**
     * @template U of object
     *
     * @param  class-string<U>  $class
     * @return FactoryBatch<U>
     */
    public static function for(string $class): self
    {
        return new self($class);
    }

    /**
     * Set the number of objects to create
     *
     * @return $this
     *
     * @throws InvalidArgumentException If count is less than 1
     */
    public function count(int $times): self
    {
        if ($times < 1) {
            throw new InvalidArgumentException('Count must be at least 1');
        }

        $this->batchCount = $times;

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
     * Set a value for a property on all objects in the batch
     *
     * @return $this
     */
    public function with(string $property, mixed $value): self
    {
        $this->values[$property] = $value;

        return $this;
    }

    /**
     * Set multiple values at once for all objects in the batch
     *
     * @param  array<string, mixed>  $values
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
     * Create the batch of objects
     *
     * @return array<int, T>
     *
     * @throws ReflectionException|ValidationException
     */
    public function make(): array
    {
        $result = [];

        for ($i = 0; $i < $this->batchCount; $i++) {
            $factory = FactoryManager::from($this->class)
                ->withValues($this->values);

            if ($this->shouldFillRandom) {
                $factory->fillRandom();
            }

            $result[] = $factory->make();
        }

        return $result;
    }
}
