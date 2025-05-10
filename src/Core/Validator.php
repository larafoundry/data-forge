<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Validator as LaravelValidator;
use Ws\DataBridge\Exceptions\ValidationException;

final class Validator
{
    private array $rules = [];
    private array $messages = [];
    private array $errors = [];
    private ValidationFactory $validatorFactory;

    public function __construct(readonly DtoInspector $dtoInspector, readonly array $attributes) 
    {
        $this->validatorFactory = $this->createValidatorFactory();
    }

    public static function from(DtoInspector $dtoInspector, array $attributes): self
    {
        return new self($dtoInspector, $attributes);
    }

    public function withRules(array $rules): self
    {
        $this->rules = array_merge($this->rules, $rules);

        return $this;
    }

    public function withMessages(array $messages): self
    {
        $this->messages = array_merge($this->messages, $messages);

        return $this;
    }

    /**
     * Get all validation errors
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validate the data and return validated data
     *
     * @return array validated data
     *
     * @throws ValidationException if validation fails
     */
    public function validate(): array
    {
        // Get required keys to add required rule
        $requiredKeys = $this->dtoInspector->getRequiredKeys();
        $laravelRules = $this->convertToLaravelRules($requiredKeys);
        
        // Create Laravel validator instance
        $validator = $this->validatorFactory->make($this->attributes, $laravelRules, $this->messages);
        
        // Add custom validation rules
        $validator->addExtension('min', function ($attribute, $value, $parameters) {
            $min = $parameters[0] ?? 0;
            if (is_string($min)) {
                $min = (int)$min;  // Convert string parameter to integer
            }
            
            if (is_string($value)) {
                return mb_strlen($value) >= $min;
            }
            
            if (is_numeric($value)) {
                return $value >= $min;
            }
            
            if (is_array($value)) {
                return count($value) >= $min;
            }
            
            return false;
        });
        
        $validator->addExtension('max', function ($attribute, $value, $parameters) {
            $max = $parameters[0] ?? PHP_INT_MAX;
            if (is_string($max)) {
                $max = (int)$max;
            }
            
            if (is_string($value)) {
                return mb_strlen($value) <= $max;
            }
            
            if (is_numeric($value)) {
                return $value <= $max;
            }
            
            if (is_array($value)) {
                return count($value) <= $max;
            }
            
            return false;
        });
        
        $validator->addExtension('email', function ($attribute, $value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        });
        
        $validator->addExtension('url', function ($attribute, $value) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        });
        
        $validator->after(function (LaravelValidator $validatorInstance) {
            foreach ($this->attributes as $key => $value) {
                if (!$this->dtoInspector->isTypeAcceptedForKey($key, $value)) {
                    $validatorInstance->errors()->add($key, "The $key field has an invalid type.");
                }
                
                if (isset($this->rules[$key])) {
                    $rules = $this->rules[$key];
                    
                    // Handle max rule manually
                    if (isset($rules['max'])) {
                        $max = $rules['max'];
                        
                        if (is_string($value) && mb_strlen($value) > $max) {
                            $validatorInstance->errors()->add($key, "The $key must not exceed $max characters.");
                        } elseif (is_numeric($value) && $value > $max) {
                            $validatorInstance->errors()->add($key, "The $key must not be greater than $max.");
                        } elseif (is_array($value) && count($value) > $max) {
                            $validatorInstance->errors()->add($key, "The $key must not have more than $max items.");
                        }
                    }
                    
                    if (is_array($rules)) {
                        foreach ($rules as $rule) {
                            if (is_array($rule) && count($rule) >= 2 && $rule[0] === 'max') {
                                $max = $rule[1];
                                
                                if (is_string($value) && mb_strlen($value) > $max) {
                                    $validatorInstance->errors()->add($key, "The $key must not exceed $max characters.");
                                } elseif (is_numeric($value) && $value > $max) {
                                    $validatorInstance->errors()->add($key, "The $key must not be greater than $max.");
                                } elseif (is_array($value) && count($value) > $max) {
                                    $validatorInstance->errors()->add($key, "The $key must not have more than $max items.");
                                }
                            }
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
            throw new ValidationException($this->errors);
        }
        
        return $validator->validated();
    }

    /**
     * @return array|false validated data or false if validation fails
     */
    public function validateSafe(): array|false
    {
        try {
            return $this->validate();
        } catch (ValidationException) {
            return false;
        }
    }
    
    /**
     * @param array $requiredKeys List of fields that are required
     * @return array Laravel validation rules
     */
    private function convertToLaravelRules(array $requiredKeys): array
    {
        $laravelRules = [];
        
        foreach ($requiredKeys as $key) {
            $laravelRules[$key][] = 'required';
        }
        
        foreach ($this->rules as $field => $fieldRules) {
            if (!isset($laravelRules[$field])) {
                $laravelRules[$field] = [];
            }
            
            if (is_string($fieldRules)) {
                $rules = explode('|', $fieldRules);
                foreach ($rules as $rule) {
                    $laravelRules[$field][] = $rule;
                }
                continue;
            }
            
            foreach ($fieldRules as $ruleKey => $ruleValue) {
                if ($ruleKey === 'min' || $ruleKey === 'max') {
                    $laravelRules[$field][] = "$ruleKey:$ruleValue";
                    continue;
                }
                
                if (is_string($ruleKey) && is_bool($ruleValue) && $ruleValue) {
                    $laravelRules[$field][] = $ruleKey;
                }
                elseif (is_string($ruleKey) && !is_bool($ruleValue)) {
                    $laravelRules[$field][] = $ruleKey . ':' . $ruleValue;
                }
                elseif (is_array($ruleValue) && count($ruleValue) >= 2) {
                    $ruleName = $ruleValue[0];
                    $parameter = $ruleValue[1];
                    
                    if (is_array($parameter)) {
                        $laravelRules[$field][] = $ruleName . ':' . implode(',', $parameter);
                    } else {
                        $laravelRules[$field][] = $ruleName . ':' . $parameter;
                    }
                }
                elseif (is_int($ruleKey) && is_string($ruleValue)) {
                    $laravelRules[$field][] = $ruleValue;
                }
            }
        }
        
        foreach (array_keys($this->attributes) as $attributeKey) {
            if (!isset($laravelRules[$attributeKey])) {
                if (!in_array($attributeKey, $requiredKeys, true)) {
                     $laravelRules[$attributeKey][] = 'nullable';
                }
            }
        }

        foreach ($laravelRules as $field => $rules) {
            $laravelRules[$field] = implode('|', $rules);
        }
        
        return $laravelRules;
    }
    
    /**
     * Create a validator factory instance
     *
     * @return ValidationFactory
     */
    private function createValidatorFactory(): ValidationFactory
    {
        $translator = new Translator(new ArrayLoader(), 'en');
        $validatorFactory = new ValidationFactory($translator, new Container());
        
        return $validatorFactory;
    }
}
