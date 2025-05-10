<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use InvalidArgumentException;
use Ws\DataBridge\Exceptions\ValidationException;

final class Validator
{
    /** @var array<string,string[]> */
    private array $rules = [];

    /** @var array<string,string> */
    private array $messages = [];

    /** @var array<string,string[]> */
    private array $errors = [];

    private ValidationFactory $factory;

    public function __construct(
        private readonly DtoInspector $inspector,
        private readonly array $attributes,
        ?ValidationFactory $factory = null,
    ) {
        // tạo factory cục bộ, không singleton
        $this->factory = $factory ?? new ValidationFactory(
            new Translator(new ArrayLoader(), 'en'),
            new Container()
        );
    }

    public static function from(DtoInspector $inspector, array $attributes): self
    {
        return new self($inspector, $attributes);
    }

    /* ------------------------------------------------------------------ */
    /*  Fluent helpers                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Thêm / ghi đè rules cho từng field.
     * Chỉ nhận **pipe-string** hoặc **mảng phẳng string** (chuẩn Laravel).
     *
     * @param array<string,string|array> $rules
     */
    public function withRules(array $rules): self
    {
        foreach ($rules as $field => $fieldRules) {
            $normalized = DtoRuleBuilder::normalizeFieldRules($fieldRules);

            $this->rules[$field] = isset($this->rules[$field])
                ? array_merge($this->rules[$field], $normalized)
                : $normalized;
        }

        return $this;
    }

    /**
     * @param array<string,string> $messages
     */
    public function withMessages(array $messages): self
    {
        $this->messages = array_merge($this->messages, $messages);

        return $this;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string,string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validate dữ liệu, trả mảng đã lọc.
     *
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public function validate(): array
    {
        $rules = DtoRuleBuilder::build($this->inspector, $this->rules, $this->attributes);

        $validator = $this->factory->make($this->attributes, $rules, $this->messages);

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
            throw new ValidationException($this->errors);
        }

        return $validator->validated();
    }

    /**
     * Validate “an toàn”: trả false khi lỗi.
     *
     * @return array<string,mixed>|false
     */
    public function validateSafe(): array|false
    {
        try {
            return $this->validate();
        } catch (ValidationException) {
            return false;
        }
    }
}
