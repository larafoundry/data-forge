<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class TypeSpec
{
    public function __construct(public readonly ?ReflectionType $reflectionType) {}

    public function accepts(mixed $value): bool
    {
        return $this->reflectionType === null || self::typeAccepts($this->reflectionType, $value);
    }

    public function allowsNull(): bool
    {
        return $this->reflectionType?->allowsNull() ?? false;
    }

    public function isNumeric(): bool
    {
        return $this->sizeRuleType() === 'numeric';
    }

    public function sizeRuleType(): ?string
    {
        if ($this->reflectionType instanceof ReflectionNamedType) {
            return self::namedSizeRuleType($this->reflectionType);
        }

        if (! ($this->reflectionType instanceof ReflectionUnionType)) {
            return null;
        }

        $resolvedType = null;
        foreach ($this->reflectionType->getTypes() as $unionType) {
            if (! ($unionType instanceof ReflectionNamedType)) {
                return null;
            }

            if ($unionType->getName() === 'null') {
                continue;
            }

            $currentType = self::namedSizeRuleType($unionType);
            if ($currentType === null) {
                return null;
            }

            if ($resolvedType !== null && $resolvedType !== $currentType) {
                return null;
            }

            $resolvedType = $currentType;
        }

        return $resolvedType;
    }

    /**
     * @return class-string|null
     */
    public function namedClass(): ?string
    {
        if (! ($this->reflectionType instanceof ReflectionNamedType) || $this->reflectionType->isBuiltin()) {
            return null;
        }

        /** @var class-string $class */
        $class = $this->reflectionType->getName();

        return $class;
    }

    private static function namedSizeRuleType(ReflectionNamedType $type): ?string
    {
        return match ($type->getName()) {
            'int', 'float' => 'numeric',
            'string' => 'string',
            default => null,
        };
    }

    private static function typeAccepts(ReflectionType $type, mixed $value): bool
    {
        if ($type instanceof ReflectionNamedType) {
            return self::namedTypeAccepts($type, $value);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if (self::typeAccepts($unionType, $value)) {
                    return true;
                }
            }

            return false;
        }

        // PHP only has named, union, and intersection types.
        $intersectionTypes = $type instanceof ReflectionIntersectionType ? $type->getTypes() : [];
        foreach ($intersectionTypes as $intersectionType) {
            if (! self::typeAccepts($intersectionType, $value)) {
                return false;
            }
        }

        return $intersectionTypes !== [];
    }

    private static function namedTypeAccepts(ReflectionNamedType $type, mixed $value): bool
    {
        $name = $type->getName();
        if ($name === 'mixed') {
            return true;
        }

        if ($value === null) {
            return $type->allowsNull();
        }

        /** @var class-string $name */
        if (! $type->isBuiltin()) {
            return $value instanceof $name;
        }

        if ($name === 'false') {
            return $value === false;
        }

        if ($name === 'true') {
            return $value === true;
        }

        if ($name === 'float') {
            return is_float($value) || is_int($value);
        }

        return self::normalizedValueType($value) === $name;
    }

    private static function normalizedValueType(mixed $value): string
    {
        return match (gettype($value)) {
            'integer' => 'int',
            'boolean' => 'bool',
            'double' => 'float',
            default => gettype($value),
        };
    }
}
