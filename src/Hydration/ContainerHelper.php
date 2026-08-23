<?php

declare(strict_types=1);

namespace Axiom\DataForge\Hydration;

use Axiom\DataForge\Exceptions\InstantiationException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

final class ContainerHelper
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  array<string,mixed>  $data
     * @return T
     *
     * @throws InstantiationException
     */
    public static function makeInstance(string $class, array $data)
    {
        if (! class_exists($class)) {
            throw new InstantiationException("Class $class not found");
        }

        $ref = new ReflectionClass($class);

        $usedKeys = [];
        $constructorArguments = [];

        $ctor = $ref->getConstructor();
        if ($ctor !== null) {
            $params = $ctor->getParameters();
            foreach ($params as $param) {
                $name = $param->getName();

                if (array_key_exists($name, $data)) {
                    $constructorArguments[] = $data[$name];
                    $usedKeys[] = $name;
                } elseif ($param->isDefaultValueAvailable()) {
                    $constructorArguments[] = $param->getDefaultValue();
                } elseif (self::parameterAllowsNull($param)) {
                    $constructorArguments[] = null;
                } else {
                    throw new InstantiationException("Missing required parameter: $name");
                }
            }
            try {
                $instance = $ref->newInstanceArgs($constructorArguments);
            } catch (Throwable $e) {
                throw new InstantiationException("Failed to instantiate $class", 0, $e);
            }
        } else {
            try {
                $instance = $ref->newInstance();
            } catch (Throwable $e) {
                throw new InstantiationException("Failed to instantiate $class", 0, $e);
            }
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $usedKeys, true) || ! property_exists($class, $key)) {
                continue;
            }

            $property = $ref->getProperty($key);
            if ($property->isStatic()) {
                continue;
            }

            if ($property->isPublic()) {
                if ($property->isReadOnly()) {
                    throw new InstantiationException("Cannot assign readonly property: $key");
                }

                try {
                    $instance->{$key} = $value;
                } catch (Throwable $e) {
                    throw new InstantiationException("Failed to assign property '$key' on $class", 0, $e);
                }
            }
        }

        foreach ($ref->getProperties() as $property) {
            if ($property->isStatic() || ! $property->isPublic() || $property->isInitialized($instance)) {
                continue;
            }

            $propertyName = $property->getName();

            if ($property->isReadOnly()) {
                throw new InstantiationException("Cannot initialize readonly property: $propertyName");
            }

            if (self::typeAllowsNull($property->getType())) {
                try {
                    $instance->{$propertyName} = null;
                } catch (Throwable $e) {
                    throw new InstantiationException("Failed to initialize nullable property '$propertyName' on $class", 0, $e);
                }

                continue;
            }

            throw new InstantiationException("Property '$propertyName' was not initialized on $class");
        }

        /** @var T */
        return $instance;
    }

    private static function parameterAllowsNull(ReflectionParameter $param): bool
    {
        return self::typeAllowsNull($param->getType());
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
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() === 'null') {
                    return true;
                }
            }
        }

        return false;
    }
}
