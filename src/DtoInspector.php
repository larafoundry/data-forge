<?php

declare(strict_types=1);

namespace Ws\DataBridge;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * @template T of object
 *
 * @param  class-string<T>  $class
 */
final class DtoInspector
{
    private ReflectionClass $reflection;

    /**
     * @param  class-string<T>  $class
     *
     * @throws ReflectionException
     */
    public function __construct(readonly string $class)
    {
        $this->reflection = new ReflectionClass($class);
    }

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
}
