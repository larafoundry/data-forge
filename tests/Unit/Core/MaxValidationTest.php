<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Tests\Unit\Core\Objects\TestDto;
use Ws\DataBridge\Core\DtoInspector;
use Ws\DataBridge\Core\DtoRuleBuilder;
use Ws\DataBridge\Core\Validator;
use Ws\DataBridge\Exceptions\ValidationException;

test('max validation works correctly', function () {
    $inspector = new DtoInspector(TestDto::class);
    
    $validator = new Validator($inspector, [
        'name' => 'John',
        'age' => 100,
    ]);
    
    $validator->withRules([
        'age' => 'numeric|max:50',
    ]);
    
    expect(function () use ($validator) {
        $validator->validate();
    })->toThrow(ValidationException::class);
});

test('max validation rules are properly built', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, [
        'name' => 'John',
        'age' => 100,
    ]);
    
    $validator->withRules([
        'age' => 'max:50',
    ]);
    
    $rules = DtoRuleBuilder::build($inspector, $validator->getCustomRules(), $validator->getAttributes());
    
    $ageRules = array_filter($rules['age'], function ($rule) {
        return is_string($rule) && str_starts_with($rule, 'max:');
    });
    
    expect($ageRules)->not->toBeEmpty();
});

test('direct Laravel validation with max rule works', function () {
    $factory = new Factory(
        new Translator(
            new ArrayLoader(), 'en'
        ),
        new Container()
    );
    
    $validator = $factory->make(
        ['age' => 100], 
        ['age' => 'numeric|max:50']
    );
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('age'))->not->toBeEmpty();
});

test('manually check if age is over max', function () {
    $inspector = new DtoInspector(TestDto::class);
    $validator = new Validator($inspector, [
        'name' => 'John',
        'age' => 100,
    ]);
    
    $validator->withRules([
        'age' => 'max:50',
    ]);
    
    // Manually validate age value against max:50
    $age = $validator->getAttributes()['age'];
    expect($age)->toBe(100)
        ->and($age > 50)->toBeTrue("Age $age should be greater than 50");
});