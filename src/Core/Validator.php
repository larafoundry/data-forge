<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

final class Validator
{
    private array $rules = [];

    private array $messages = [];

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
     * @return array<string, string[]>
     */
    public function validate(): array
    {
        $errors = [];

        $requiredKeys = $this->dtoInspector->getRequiredKeys();
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $this->attributes)) {
                $errors[$key] = [$this->getMessage("$key.required", "The $key field is required.")];
            }
        }

        foreach ($this->attributes as $key => $value) {
            if (! $this->dtoInspector->isTypeAcceptedForKey($key, $value)) {
                $errors[$key] = [$this->getMessage("$key.type", "The $key field has an invalid type.")];
            }
        }

        foreach ($this->rules as $field => $fieldRules) {
            if (! array_key_exists($field, $this->attributes)) {
                continue;
            }

            $value = $this->attributes[$field];

            foreach ($fieldRules as $rule => $ruleParams) {
                if (! $this->validateRule($rule, $value, $ruleParams)) {
                    $errors[$field][] = $this->getMessage(
                        "$field.$rule",
                        "The $field field validation failed for rule: $rule."
                    );
                }
            }
        }

        return $errors;
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
