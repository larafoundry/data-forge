<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use ReflectionClass;
use ReflectionProperty;
use ReflectionType;

/**
 * @template T of object
 *
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
final class DtoInspector
{
    /**
     * @var ReflectionClass<T>
     */
    private readonly ReflectionClass $reflection;

    /**
     * @var DtoSchema<T>
     */
    private readonly DtoSchema $schema;

    /**
     * @param  class-string<T>  $class
     */
    public function __construct(public readonly string $class)
    {
        $this->schema = DtoSchemaCompiler::compile($class);
        $this->reflection = $this->schema->reflection;
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
        return $this->schema->acceptedKeys();
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredKeys(): array
    {
        return $this->schema->requiredKeys();
    }

    /**
     * Validation rules declared on the DTO, resolved once at schema compile time.
     *
     * @return array<string, string|array<int, RuleValue>>
     */
    public function getRules(): array
    {
        return $this->schema->rules;
    }

    /**
     * Custom validation messages declared on the DTO, resolved once at schema
     * compile time.
     *
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return $this->schema->messages;
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function isTypeAcceptedForKey(string $key, mixed $value): bool
    {
        return $this->schema->field($key)?->type->accepts($value) ?? false;
    }

    /**
     * Get the mapped key for a property if it has a MapKey attribute.
     * Otherwise, return the property name.
     */
    public function getMappedKey(string $propertyName): string
    {
        $field = $this->schema->field($propertyName);

        return $field === null ? $propertyName : $field->inputKey;
    }

    /**
     * Get a map of property names to input keys.
     *
     * @return array<string, string>
     *
     * @throws InspectionException
     */
    public function getKeyMap(): array
    {
        return $this->schema->keyMap();
    }

    public function getReflectionProperty(string $name): ?ReflectionProperty
    {
        return $this->schema->field($name)?->property;
    }

    /**
     * @throws InspectionException
     */
    public function getReflectionPropertyOrFail(string $name): ReflectionProperty
    {
        $property = $this->getReflectionProperty($name);
        if ($property === null) {
            throw new InspectionException(
                "Property '$name' does not exist in class '{$this->reflection->getName()}'"
            );
        }

        return $property;
    }

    public function getTypeForKey(string $key): ?ReflectionType
    {
        return $this->getTypeSpecForKey($key)?->reflectionType;
    }

    public function getTypeSpecForKey(string $key): ?TypeSpec
    {
        return $this->schema->field($key)?->type;
    }

    /**
     * @return class-string|null
     *
     * @throws InspectionException
     */
    public function getArrayItemClassForKey(string $key): ?string
    {
        return $this->schema->field($key)?->arrayItemClass;
    }
}
