<?php

declare(strict_types=1);

namespace Ws\DataBridge;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

abstract class DataTransferObject
{
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];

        $args = [];
        foreach ($parameters as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $args[$name] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
            } else {
                throw new InvalidArgumentException("Thiếu tham số bắt buộc: $name");
            }
        }

        $dto = new static(...$args);
        self::validateScalarProperties($dto);

        return $dto;
    }

    /**
     * Quét và kiểm tra các thuộc tính built‑in của DTO.
     */
    private static function validateScalarProperties(object $dto): void
    {
        $reflection = new ReflectionClass($dto);

        foreach (
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property
        ) {
            if ($property->isStatic()) {
                continue;                           // bỏ qua static
            }

            $type = $property->getType();
            if ($type === null) {
                continue;                           // không khai báo kiểu ⇒ skip
            }

            // Hỗ trợ union type (ví dụ int|string|null)
            $types = $type instanceof ReflectionUnionType
                ? $type->getTypes()
                : [$type];

            $property->setAccessible(true);
            $value = $property->getValue($dto);

            $match = false;
            foreach ($types as $t) {
                /** @var ReflectionNamedType $t */
                if ($t->getName() === 'null' && $value === null) {
                    $match = true;
                    break;
                }

                if (! $t->isBuiltin()) {            // chỉ check built‑in
                    $match = true;
                    break;
                }

                $match = match ($t->getName()) {
                    'int' => is_int($value),
                    'float' => is_float($value),
                    'string' => is_string($value),
                    'bool' => is_bool($value),
                    'array' => is_array($value),
                    'callable' => is_callable($value),
                    'object' => is_object($value),
                    default => true,              // các built‑in hiếm gặp khác
                };

                if ($match) {
                    break;
                }
            }

            if (! $match) {
                $expected = implode(
                    '|',
                    array_map(fn ($t) => $t->getName(), $types)
                );
                throw new InvalidArgumentException(
                    sprintf(
                        'Property `%s` expects [%s], %s given',
                        $property->getName(),
                        $expected,
                        get_debug_type($value)
                    )
                );
            }
        }
    }
}
