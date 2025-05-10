<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use InvalidArgumentException;
use Tests\Unit\Core\Objects\TestDto;
use Ws\DataBridge\Core\DtoInspector;
use Ws\DataBridge\Core\DtoRuleBuilder;

test('normalizeFieldRules handles pipe-string correctly', function () {
    $rules = DtoRuleBuilder::normalizeFieldRules('required|min:3|max:10');
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveCount(3)
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('min:3')
        ->and($rules)->toContain('max:10');
});

test('normalizeFieldRules handles array of strings correctly', function () {
    $rules = DtoRuleBuilder::normalizeFieldRules(['required', 'min:3', 'max:10']);
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveCount(3)
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('min:3')
        ->and($rules)->toContain('max:10');
});

test('normalizeFieldRules throws exception for invalid array items', function () {
    $this->expectException(InvalidArgumentException::class);
    
    DtoRuleBuilder::normalizeFieldRules(['required', ['min', 3], 'max:10']);
});

test('build creates required rules', function () {
    $inspector = new DtoInspector(TestDto::class);
    $rules = DtoRuleBuilder::build($inspector, [], []);
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('age')
        ->and($rules['name'])->toContain('required')
        ->and($rules['age'])->toContain('required');
});

test('build handles optional properties', function () {
    $inspector = new DtoInspector(TestDto::class);
    $rules = DtoRuleBuilder::build($inspector, [], []);
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('email')
        ->and($rules)->toHaveKey('url');
    
    // Type validation closures should be present
    expect(count($rules['email']))->toBeGreaterThanOrEqual(1);
    expect(count($rules['url']))->toBeGreaterThanOrEqual(1);
});

test('build merges custom rules', function () {
    $inspector = new DtoInspector(TestDto::class);
    $customRules = [
        'name' => ['min:3', 'max:50'],
        'email' => ['email'],
    ];
    
    $rules = DtoRuleBuilder::build($inspector, $customRules, []);
    
    expect($rules)->toBeArray()
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('min:3')
        ->and($rules['name'])->toContain('max:50')
        ->and($rules['email'])->toContain('email');
});

test('build adds nullable rule for unknown payload keys', function () {
    $inspector = new DtoInspector(TestDto::class);
    $payload = [
        'name' => 'John',
        'age' => 30,
        'unknown_field' => 'some value',
    ];
    
    $rules = DtoRuleBuilder::build($inspector, [], $payload);
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('unknown_field')
        ->and($rules['unknown_field'])->toContain('nullable');
});

test('build adds type validation', function () {
    $inspector = new DtoInspector(TestDto::class);
    $rules = DtoRuleBuilder::build($inspector, [], []);
    
    expect($rules)->toBeArray()
        ->and(count($rules['name']))->toBeGreaterThanOrEqual(1)
        ->and(count($rules['age']))->toBeGreaterThanOrEqual(1);
    
    // At least one element in each property's rules should be a Closure
    $nameHasClosure = false;
    $ageHasClosure = false;
    
    foreach ($rules['name'] as $rule) {
        if ($rule instanceof \Closure) {
            $nameHasClosure = true;
            break;
        }
    }
    
    foreach ($rules['age'] as $rule) {
        if ($rule instanceof \Closure) {
            $ageHasClosure = true;
            break;
        }
    }
    
    expect($nameHasClosure)->toBeTrue();
    expect($ageHasClosure)->toBeTrue();
}); 