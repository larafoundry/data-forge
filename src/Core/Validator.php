<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

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

            foreach ($fieldRules as $rule => $ruleParams) {
                if (! $this->validateRule($rule, $value, $ruleParams)) {
                    $this->errors[$field][] = $this->getMessage(
                        "$field.$rule",
                        "The $field field validation failed for rule: $rule."
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

    private function getMessage(string $key, string $default): string
    {
        return $this->messages[$key] ?? $default;
    }

    private function validateRule(string $rule, mixed $value, mixed $params): bool
    {
        return match ($rule) {
            'min' => is_numeric($value) ? $value >= $params : (is_string($value) && mb_strlen($value) >= $params),
            'max' => is_numeric($value) ? $value <= $params : (is_string($value) && mb_strlen($value) <= $params),
            'in' => in_array($value, (array) $params),
            'regex' => is_string($value) && preg_match($params, $value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => false,
        };
    }
}
