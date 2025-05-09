<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Ws\DataBridge\Exceptions\ValidationException;

final class Validator
{
    private array $rules = [];

    private array $messages = [];

    private array $errors = [];

    public function __construct(readonly DtoInspector $dtoInspector, readonly array $attributes) {}

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
        $this->errors = [];
        $validatedData = [];

        // Validate required fields
        $requiredKeys = $this->dtoInspector->getRequiredKeys();
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $this->attributes)) {
                $this->errors[$key] = [$this->getMessage("$key.required", "The $key field is required.")];
            }
        }

        // Validate field types
        foreach ($this->attributes as $key => $value) {
            if (! $this->dtoInspector->isTypeAcceptedForKey($key, $value)) {
                $this->errors[$key] = [$this->getMessage("$key.type", "The $key field has an invalid type.")];
            } else {
                $validatedData[$key] = $value;
            }
        }

        // Apply custom rules
        foreach ($this->rules as $field => $fieldRules) {
            if (! array_key_exists($field, $this->attributes)) {
                continue;
            }

            $value = $this->attributes[$field];

            foreach ($fieldRules as $ruleIndex => $ruleInfo) {
                // Handle simple string rules like 'required', 'email'
                if (is_string($ruleInfo)) {
                    $rule = $ruleInfo;
                    $ruleParams = null;
                } 
                // Handle array rules like ['min', 3]
                elseif (is_array($ruleInfo) && count($ruleInfo) >= 2) {
                    $rule = $ruleInfo[0];
                    $ruleParams = $ruleInfo[1];
                }
                // Handle associative array rules like ['min' => 3]
                elseif (is_string($ruleIndex) && !is_array($ruleInfo)) {
                    $rule = $ruleIndex;
                    $ruleParams = $ruleInfo;
                } else {
                    continue; // Skip invalid rule format
                }

                // Skip the rule if it's already handled (like required/type checks)
                if (in_array($rule, ['required', 'string', 'integer']) && $this->errors) {
                    continue;
                }

                if (! $this->validateRule($rule, $value, $ruleParams)) {
                    $this->errors[$field][] = $this->getMessage(
                        "$field.$rule",
                        "The $field field validation failed for rule: $rule.",
                        $ruleParams
                    );
                }
            }
        }

        // If there are any errors, throw exception
        if (! empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $validatedData;
    }

    /**
     * Validate without throwing an exception
     *
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

    private function getMessage(string $key, string $default, mixed $params = null): string
    {
        $message = $this->messages[$key] ?? $default;
        
        // Replace placeholders in message
        if ($params !== null) {
            if (is_array($params)) {
                $params = implode(', ', $params);
            }
            $message = str_replace(':min', (string) $params, $message);
            $message = str_replace(':max', (string) $params, $message);
        }
        
        return $message;
    }

    private function validateRule(string $rule, mixed $value, mixed $params = null): bool
    {
        // Special case for the 'in' rule with multiple values
        if ($rule === 'in' && is_array($params)) {
            return in_array($value, $params, true);
        }
        
        return match ($rule) {
            'required' => !empty($value),
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
            'min' => is_numeric($value) ? $value >= $params : (is_string($value) && mb_strlen($value) >= $params),
            'max' => is_numeric($value) ? $value <= $params : (is_string($value) && mb_strlen($value) <= $params),
            'in' => in_array($value, (array) $params, true),
            'regex' => is_string($value) && preg_match($params, $value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => false,
        };
    }
}
