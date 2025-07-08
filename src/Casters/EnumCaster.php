<?php

declare(strict_types=1);

namespace Axiom\DataForge\Casters;

use Axiom\DataForge\Casters\Contracts\ICaster;
use BackedEnum;
use InvalidArgumentException;
use ReflectionEnum;
use ValueError;

/**
 * @template T of BackedEnum
 *
 * @implements ICaster<T>
 */
class EnumCaster implements ICaster
{
    /**
     * @param  class-string<T>  $enumClass
     */
    public function __construct(private string $enumClass)
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("Enum class $enumClass does not exist");
        }

        $reflection = new ReflectionEnum($enumClass);
        if (! $reflection->isBacked()) {
            throw new InvalidArgumentException("Enum class $enumClass is not backed");
        }
    }

    public function cast(mixed $value)
    {
        if ($value instanceof $this->enumClass) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            $enum = $this->enumClass;
            try {
                /** @var T */
                return $enum::from($value);
            } catch (ValueError) {
                return null;
            }
        }

        return null;
    }
}
