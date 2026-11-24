<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Axiom\DataForge\Validation\RuleScope;
use PHPUnit\Framework\TestCase;

final class RuleScopeTest extends TestCase
{
    public function test_nested_rules_keep_only_paths_below_the_field(): void
    {
        $rules = [
            'name' => ['required'],
            'address' => ['array'],
            'address.city' => ['min:2'],
            'address.zip.code' => ['digits:5'],
        ];

        $this->assertSame([
            'city' => ['min:2'],
            'zip.code' => ['digits:5'],
        ], RuleScope::nestedRules($rules, 'address'));
    }

    public function test_nested_messages_keep_only_rule_specific_messages_below_the_field(): void
    {
        $messages = [
            'name.required' => 'Name is required.',
            'address.array' => 'Address must be an array.',
            'address.city.min' => 'City is too short.',
        ];

        $this->assertSame([
            'city.min' => 'City is too short.',
        ], RuleScope::nestedMessages($messages, 'address'));
    }

    public function test_collection_item_rules_accept_numeric_rule_keys(): void
    {
        // PHP turns the array key '0' into the integer 0.
        $rules = [
            0 => ['array'],
            '0.email' => ['email'],
            '*.name' => ['min:2'],
            '1.name' => ['max:3'],
        ];

        $this->assertSame([
            'email' => ['email'],
            'name' => ['min:2'],
        ], RuleScope::collectionItemRules($rules, 0));
    }

    public function test_collection_item_messages_keep_only_rule_specific_messages_for_the_item(): void
    {
        $messages = [
            '*.name.min' => 'Name is too short.',
            '1.name.max' => 'Name is too long.',
        ];

        $this->assertSame([
            'name.min' => 'Name is too short.',
        ], RuleScope::collectionItemMessages($messages, 0));
    }
}
