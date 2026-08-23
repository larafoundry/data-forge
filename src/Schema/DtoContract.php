<?php

declare(strict_types=1);

namespace Axiom\DataForge\Schema;

use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Validation\DtoRuleBuilder;

/**
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
final class DtoContract
{
    /**
     * @param  class-string  $class
     * @return array<string,string|array<int,RuleValue>>
     *
     * @throws InspectionException
     */
    public static function rules(string $class): array
    {
        $rawRules = self::callStaticDtoMethod($class, 'rules');
        if (! is_array($rawRules)) {
            throw new InspectionException("Static rules() on $class must return an array.");
        }

        $rules = [];
        foreach ($rawRules as $field => $rule) {
            if (! is_string($field)) {
                throw new InspectionException("Validation rule field names must be strings on $class");
            }

            if (is_string($rule)) {
                $rules[$field] = $rule;

                continue;
            }

            if (is_object($rule)) {
                $rules[$field] = DtoRuleBuilder::normalizeFieldRules($rule);

                continue;
            }

            if (! is_array($rule)) {
                throw new InspectionException("Validation rules for field '$field' must be a string, supported rule object, or flat rule array");
            }

            $rules[$field] = DtoRuleBuilder::normalizeFieldRules($rule);
        }

        return $rules;
    }

    /**
     * @param  class-string  $class
     * @return array<string,string>
     *
     * @throws InspectionException
     */
    public static function messages(string $class): array
    {
        $rawMessages = self::callStaticDtoMethod($class, 'messages');
        if (! is_array($rawMessages)) {
            throw new InspectionException("Static messages() on $class must return an array.");
        }

        $messages = [];
        foreach ($rawMessages as $field => $message) {
            if (! is_string($field) || ! is_string($message)) {
                throw new InspectionException("Validation messages must be string-keyed strings on $class");
            }

            $messages[$field] = $message;
        }

        return $messages;
    }

    /**
     * @param  class-string  $class
     */
    private static function callStaticDtoMethod(string $class, string $method): mixed
    {
        if (! is_callable([$class, $method])) {
            return [];
        }

        return $class::$method();
    }
}
