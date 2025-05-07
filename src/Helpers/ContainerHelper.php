<?php

declare(strict_types=1);

namespace Ws\DataBridge\Helpers;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class ContainerHelper
{
    /**
     * @template T
     *
     * @param  class-string<T>  $class
     * @param  array<string,mixed>  $data
     * @return T
     *
     * @throws InvalidArgumentException|ReflectionException
     */
    public static function makeInstance(string $class, array $data)
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Class $class not found");
        }
        $ref = new ReflectionClass($class);
        $usedKeys = [];
        $constructorArguments = [];

        if ($ref->hasMethod('__construct')) {
            $ctor = $ref->getConstructor();
            $params = $ctor->getParameters();

            foreach ($params as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $data)) {
                    $constructorArguments[] = $data[$name];
                    $usedKeys[] = $name;
                } elseif ($param->isDefaultValueAvailable()) {
                    $constructorArguments[] = $param->getDefaultValue();
                } else {
                    throw new InvalidArgumentException("Missing required parameter: $name");
                }
            }
            $instance = $ref->newInstanceArgs($constructorArguments);
        } else {
            $instance = $ref->newInstance();
        }

        foreach ($data as $key => $value) {
            if (! in_array($key, $usedKeys) && property_exists($class, $key) && $ref->getProperty($key)->isPublic()) {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }
}
