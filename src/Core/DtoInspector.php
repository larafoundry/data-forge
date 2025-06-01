<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Ws\DataBridge\Attributes\MapKey;

/**
 * @template T of object
 */
final class DtoInspector
{
    /**
     * @var ReflectionClass<T>
     */
    private readonly ReflectionClass $reflection;

    /**
     * @param  class-string<T>  $class
     */
    public function __construct(readonly string $class)
    {
        $this->reflection = new ReflectionClass($class);
    }

    /**
     * @return ReflectionClass<T>
     */
    public function getReflection(): ReflectionClass
    {
        return $this->reflection;
    }

    /**
     * @return array<int, string>
     */
    public function getAcceptedKeys(): array
    {
        $required = [];
        $optional = [];

        $ctor = $this->reflection->getConstructor();
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $param) {
                $name = $param->getName();
                if (self::isParameterOptional($param)) {
                    $optional[] = $name;
                } else {
                    $required[] = $name;
                }
            }
        }

        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (self::propertyHasDefault($prop)) {
                $optional[] = $name;
            } else {
                $required[] = $name;
            }
        }

        $keys = array_values(array_unique(array_merge($required, $optional)));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredKeys(): array
    {
        $required = [];

        $ctor = $this->reflection->getConstructor();
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $param) {
                if (! self::isParameterOptional($param)) {
                    $required[] = $param->getName();
                }
            }
        }

        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (! self::propertyHasDefault($prop)) {
                $required[] = $prop->getName();
            }
        }

        return array_values(array_unique($required));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function isTypeAcceptedForKey(string $key, mixed $value): bool
    {
        if ($this->reflection->hasProperty($key)) {
            $prop = $this->reflection->getProperty($key);
            $t = $prop->getType();
            if ($t === null) {
                return true;
            }

            if ($t instanceof ReflectionNamedType) {
                return self::checkNamedType($t, $value);
            }

            return self::checkCompositeType($t, $value);
        }

        $ctor = $this->reflection->getConstructor();
        if ($ctor === null) {
            return false;
        }
        foreach ($ctor->getParameters() as $param) {
            if ($param->getName() === $key) {
                $t = $param->getType();
                if ($t === null) {
                    return true;
                }

                if ($t instanceof ReflectionNamedType) {
                    return self::checkNamedType($t, $value);
                }

                return self::checkCompositeType($t, $value);
            }
        }

        return false;
    }

    /**
     * Get the mapped key for a property if it has a MapKey attribute.
     * Otherwise, return the property name.
     *
     * @throws ReflectionException
     */
    public function getMappedKey(string $propertyName): string
    {
        if (! $this->reflection->hasProperty($propertyName)) {
            return $propertyName;
        }

        $property = $this->reflection->getProperty($propertyName);
        $attributes = $property->getAttributes(MapKey::class);

        if (empty($attributes)) {
            return $propertyName;
        }

        /** @var MapKey $mapKey */
        $mapKey = $attributes[0]->newInstance();

        return $mapKey->key;
    }

    /**
     * Get a map of property names to input keys.
     *
     * @return array<string, string>
     *
     * @throws ReflectionException
     */
    public function getKeyMap(): array
    {
        $map = [];

        // Check constructor parameters
        $ctor = $this->reflection->getConstructor();
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $param) {
                $name = $param->getName();
                $map[$name] = $this->getMappedKey($name);
            }
        }

        // Check public properties
        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $map[$name] = $this->getMappedKey($name);
        }

        return $map;
    }

    /**
     * @throws ReflectionException
     */
    public function getReflectionProperty(string $name): ?ReflectionProperty
    {
        if ($this->reflection->hasProperty($name)) {
            return $this->reflection->getProperty($name);
        }

        return null;
    }

    /**
     * @throws ReflectionException
     */
    public function getReflectionPropertyOrFail(string $name): ReflectionProperty
    {
        if (! $this->reflection->hasProperty($name)) {
            throw new ReflectionException("Property '$name' does not exist in class '{$this->reflection->getName()}'");
        }

        return $this->reflection->getProperty($name);
    }

    /**
     * @throws ReflectionException
     */
    public function getTypeForKey(string $key): ?ReflectionType
    {
        if ($this->reflection->hasProperty($key)) {
            $prop = $this->reflection->getProperty($key);

            return $prop->getType();
        }

        $ctor = $this->reflection->getConstructor();
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $param) {
                if ($param->getName() === $key) {
                    return $param->getType();
                }
            }
        }

        return null;
    }

    private static function isParameterOptional(ReflectionParameter $param): bool
    {
        if ($param->isDefaultValueAvailable()) {
            return true;
        }

        return self::typeAllowsNull($param->getType());
    }

    private static function propertyHasDefault(ReflectionProperty $prop): bool
    {
        return $prop->hasDefaultValue() || self::typeAllowsNull($prop->getType());
    }

    private static function typeAllowsNull(?ReflectionType $type): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type->allowsNull()) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($t instanceof ReflectionNamedType && $t->getName() === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalizeValueType(mixed $v): string
    {
        return match (gettype($v)) {
            'integer' => 'int',
            'boolean' => 'bool',
            'double' => 'float',
            default => gettype($v),
        };
    }

    private static function checkNamedType(ReflectionNamedType $t, mixed $v): bool
    {
        /** @var class-string $name */
        $name = $t->getName();
        if ($t->isBuiltin()) {
            return self::normalizeValueType($v) === $name;
        }

        return $v instanceof $name;
    }

    private static function checkCompositeType(ReflectionType $type, mixed $v): bool
    {
        if ($type instanceof ReflectionUnionType) {
            // For union types, value should match ANY of the types (OR logic)
            $types = $type->getTypes();
            foreach ($types as $t) {
                if ($t instanceof ReflectionNamedType && self::checkNamedType($t, $v)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            // For intersection types, value should match ALL of the types (AND logic)
            $types = $type->getTypes();
            foreach ($types as $t) {
                if ($t instanceof ReflectionNamedType && ! self::checkNamedType($t, $v)) {
                    return false;
                }
            }

            return ! empty($types);
        }

        return false;
    }
}
