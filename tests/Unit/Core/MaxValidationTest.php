<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

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
    
    // Dump the validator object
    dump($validator);
    
    // Apply the max rule with numeric validation
    $validator->withRules([
        'age' => 'numeric|max:50',
    ]);
    
    // Dump the validator rules
    dump($validator);
    
    // Expect validation to fail
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
    
    // Check the built rules
    $rules = DtoRuleBuilder::build($inspector, $validator->getCustomRules(), $validator->getAttributes());
    
    dump('Built rules:', $rules);
    
    // Check if max rule is included in the final rules
    $ageRules = array_filter($rules['age'], function ($rule) {
        return is_string($rule) && str_starts_with($rule, 'max:');
    });
    
    expect($ageRules)->not->toBeEmpty();
});

test('direct Laravel validation with max rule works', function () {
    // Directly test Laravel validation
    $factory = new \Illuminate\Validation\Factory(
        new \Illuminate\Translation\Translator(
            new \Illuminate\Translation\ArrayLoader(), 'en'
        ),
        new \Illuminate\Container\Container()
    );
    
    $validator = $factory->make(
        ['age' => 100], 
        ['age' => 'numeric|max:50']
    );
    
    dump("Validator errors:", $validator->errors()->toArray());
    
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('age'))->not->toBeEmpty();
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
    expect($age)->toBe(100);
    expect($age > 50)->toBeTrue("Age $age should be greater than 50");
}); 