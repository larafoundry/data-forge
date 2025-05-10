<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

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
        if (! $this->reflection->hasProperty($key)) {
            return false;
        }

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
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : ($type instanceof ReflectionIntersectionType
            ? $type->getTypes()
            : []);
        foreach ($types as $t) {
            if ($t instanceof ReflectionNamedType && self::checkNamedType($t, $v)) {
                return true;
            }
        }

        return false;
    }
}
