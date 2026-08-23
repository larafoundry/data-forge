<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Validation\DtoRuleBuilder;
use ReflectionClass;

/**
 * @template T of object
 *
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
final class DtoSchema
{
    /**
     * @param  class-string<T>  $class
     * @param  ReflectionClass<T>  $reflection
     * @param  array<string,FieldSpec>  $fields
     * @param  array<string,string|array<int,RuleValue>>  $rules
     * @param  array<string,string>  $messages
     */
    public function __construct(
        public readonly string $class,
        public readonly ReflectionClass $reflection,
        public readonly array $fields,
        public readonly array $rules,
        public readonly array $messages,
    ) {}

    /**
     * @return array<int,string>
     */
    public function acceptedKeys(): array
    {
        return array_keys($this->fields);
    }

    /**
     * @return array<int,string>
     */
    public function requiredKeys(): array
    {
        $required = [];
        foreach ($this->fields as $field) {
            if ($field->required) {
                $required[] = $field->name;
            }
        }

        return $required;
    }

    /**
     * @return array<string,string>
     */
    public function keyMap(): array
    {
        $map = [];
        foreach ($this->fields as $field) {
            $map[$field->name] = $field->inputKey;
        }

        return $map;
    }

    public function field(string $name): ?FieldSpec
    {
        return $this->fields[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }
}
