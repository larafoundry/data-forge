<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Ws\DataBridge\Exceptions\ValidationException;

final class Validator
{
    /** @var array<string, string|array> */
    private array $rules = [];

    /** @var array<string, string> */
    private array $messages = [];

    /** @var array<string, string[]> */
    private array $errors = [];

    private ValidationFactory $factory;

    public function __construct(
        private readonly DtoInspector $inspector,
        private readonly array $attributes,
        ?ValidationFactory $factory = null,
    ) {
        $this->factory = $factory ?? self::makeFactory();
    }

    public static function from(DtoInspector $inspector, array $attributes, ?ValidationFactory $factory = null): self
    {
        return new self($inspector, $attributes, $factory);
    }

    /* -----------------------------------------------------------------
     *  Fluent helpers
     * ----------------------------------------------------------------- */

    /** @param array<string, string|array> $rules */
    public function withRules(array $rules): self
    {
        $this->rules = array_merge($this->rules, $rules);
        return $this;
    }

    /** @param array<string, string> $messages */
    public function withMessages(array $messages): self
    {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }

    /* -----------------------------------------------------------------
     *  Validation
     * ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed> Dữ liệu đã lọc
     * @throws ValidationException
     */
    public function validate(): array
    {
        $rules = DtoRuleBuilder::build($this->inspector, $this->rules);

        $validator = $this->factory->make($this->attributes, $rules, $this->messages);

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
            throw new ValidationException($this->errors);
        }

        return $validator->validated();
    }

    /**
     * Validate “an toàn”: trả về false nếu lỗi
     *
     * @return array<string, mixed>|false
     */
    public function validateSafe(): array|false
    {
        try {
            return $this->validate();
        } catch (ValidationException) {
            return false;
        }
    }

    /* -----------------------------------------------------------------
     *  Errors
     * ----------------------------------------------------------------- */

    /** @return array<string, string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /* -----------------------------------------------------------------
     *  Internal
     * ----------------------------------------------------------------- */

    private static function makeFactory(): ValidationFactory
    {
        $translator = new Translator(new ArrayLoader(), 'en');
        return new ValidationFactory($translator, new Container());
    }
}
