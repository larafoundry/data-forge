<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Ws\DataBridge\Core\DtoInspector;

final class ContainerHelper
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  array<string,mixed>  $data
     * @return T
     *
     * @throws InvalidArgumentException|ReflectionException
     */
    public static function makeInstance(string $class, array $data): object
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Class $class not found");
        }

        $ref = new ReflectionClass($class);
        $inspector = new DtoInspector($class);
        $keyMap = $inspector->getKeyMap();
        $reverseKeyMap = array_flip($keyMap);

        // Create a new data array with mapped keys
        $mappedData = [];
        foreach ($data as $key => $value) {
            if (isset($reverseKeyMap[$key])) {
                // If this is a mapped key, use the property name
                $mappedData[$reverseKeyMap[$key]] = $value;
            } else {
                // Otherwise, keep the original key
                $mappedData[$key] = $value;
            }
        }

        $usedKeys = [];
        $constructorArguments = [];

        $ctor = $ref->getConstructor();
        if ($ctor !== null) {
            $params = $ctor->getParameters();
            foreach ($params as $param) {
                $name = $param->getName();
                $mappedKey = $keyMap[$name] ?? $name;

                if (array_key_exists($name, $mappedData)) {
                    $constructorArguments[] = $mappedData[$name];
                    $usedKeys[] = $name;
                } elseif (array_key_exists($mappedKey, $data)) {
                    $constructorArguments[] = $data[$mappedKey];
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

        foreach ($mappedData as $key => $value) {
            if (! in_array($key, $usedKeys) && property_exists($class, $key) && $ref->getProperty($key)->isPublic()) {
                $instance->{$key} = $value;
            }
        }

        /** @var T */
        return $instance;
    }
}
