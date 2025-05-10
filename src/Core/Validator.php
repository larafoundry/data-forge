<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
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
        $attributes = self::autoCastAttributes($inspector, $attributes);
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
     * @return array<string,string[]>
     */
    public function getCustomRules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
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
     * Validate "an toàn": trả false khi lỗi.
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

    /**
     * Auto-cast attributes to their respective types based on the class constructor parameters.
     *
     * @param DtoInspector $inspector
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    private static function autoCastAttributes(DtoInspector $inspector, array $attributes): array
    {
        $reflection = new ReflectionClass($inspector->getReflection()->getName());
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return $attributes;
        }
        
        foreach ($constructor->getParameters() as $param) {
            $attributes = self::tryCastParameter($attributes, $param);
        }
        
        return $attributes;
    }

    /**
     * Try to cast a parameter value to its appropriate type.
     *
     * @param array<string,mixed> $attributes
     * @param ReflectionParameter $param
     * @return array<string,mixed>
     */
    private static function tryCastParameter(array $attributes, ReflectionParameter $param): array
    {
        $name = $param->getName();
        $type = $param->getType();
        
        if (!isset($attributes[$name]) || !$type || !($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return $attributes;
        }
        
        // Skip if the value is already of the correct type
        $typeName = $type->getName();
        if (is_object($attributes[$name]) && $attributes[$name] instanceof $typeName) {
            return $attributes;
        }
        
        $attributes[$name] = self::castValue($attributes[$name], $typeName);
        
        return $attributes;
    }

    /**
     * Cast a value to the specified type if possible.
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private static function castValue(mixed $value, string $type): mixed
    {
        // Handle backed enums
        if (enum_exists($type)) {
            $reflectionEnum = new ReflectionEnum($type);
            if ($reflectionEnum->isBacked() && is_string($value)) {
                return $type::from($value);
            }
        }
        
        // Handle datetime types
        if (is_string($value)) {
            return match($type) {
                Carbon::class => Carbon::parse($value),
                CarbonImmutable::class => CarbonImmutable::parse($value),
                DateTime::class => new DateTime($value),
                DateTimeImmutable::class => new DateTimeImmutable($value),
                default => $value
            };
        }
        
        return $value;
    }
}
