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
}
