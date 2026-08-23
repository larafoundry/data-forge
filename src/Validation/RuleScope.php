<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

use Axiom\DataForge\Input\Path;

/**
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
final class RuleScope
{
    /**
     * @param  array<string,array<int,RuleValue>>  $rules
     * @return array<string,array<int,RuleValue>>
     */
    public static function nestedRules(array $rules, string $field): array
    {
        $scoped = [];
        $prefix = Path::fromString($field);

        foreach ($rules as $path => $fieldRules) {
            $nestedPath = Path::fromString($path)->relativeTo($prefix);
            if ($nestedPath === null || $nestedPath->isEmpty()) {
                continue;
            }

            $scoped[$nestedPath->toString()] = $fieldRules;
        }

        return $scoped;
    }

    /**
     * @param  array<string,string>  $messages
     * @return array<string,string>
     */
    public static function nestedMessages(array $messages, string $field): array
    {
        $scoped = [];
        $prefix = Path::fromString($field);

        foreach ($messages as $path => $message) {
            $nestedPath = Path::fromString($path)->relativeTo($prefix);
            if ($nestedPath === null || $nestedPath->isEmpty() || ! $nestedPath->containsNestedPath()) {
                continue;
            }

            $scoped[$nestedPath->toString()] = $message;
        }

        return $scoped;
    }

    /**
     * @param  array<string,array<int,RuleValue>>  $rules
     * @return array<string,array<int,RuleValue>>
     */
    public static function collectionItemRules(array $rules, int $index): array
    {
        $scoped = [];

        foreach ($rules as $path => $fieldRules) {
            $itemPath = Path::fromString($path)->collectionItemRelative($index);
            if ($itemPath !== null) {
                $scoped[$itemPath->toString()] = $fieldRules;
            }
        }

        return $scoped;
    }

    /**
     * @param  array<string,string>  $messages
     * @return array<string,string>
     */
    public static function collectionItemMessages(array $messages, int $index): array
    {
        $scoped = [];

        foreach ($messages as $path => $message) {
            $itemPath = Path::fromString($path)->collectionItemRelative($index);
            if ($itemPath !== null && $itemPath->containsNestedPath()) {
                $scoped[$itemPath->toString()] = $message;
            }
        }

        return $scoped;
    }
}
