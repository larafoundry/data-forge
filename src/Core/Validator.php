<?php

declare(strict_types=1);

namespace Ws\DataBridge\Core;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Ws\DataBridge\Exceptions\ValidationException;

final class Validator
{
    /** @var array<string,string[]> */
    private array $rules = [];

    /** @var array<string,string> */
    private array $messages = [];

    /** @var array<string,array<string>> */
    private array $errors = [];

    private ValidationFactory $factory;

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     */
    public function __construct(
        private readonly DtoInspector $inspector,
        private readonly array $attributes,
        ?ValidationFactory $factory = null,
    ) {
        $this->factory = $factory ?? new ValidationFactory(
            new Translator(new ArrayLoader(), 'en'),
            new Container()
        );
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     *
     * @throws ReflectionException
     */
    public static function from(DtoInspector $inspector, array $attributes): self
    {
        $attributes = self::autoCastAttributes($inspector, $attributes);

        return new self($inspector, $attributes);
    }

    /**
     * @param  array<string,string|array<string>>  $rules
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
     * @param  array<string,string>  $messages
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
     * @return array<string,mixed>
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        $rules = DtoRuleBuilder::build($this->inspector, $this->rules, $this->attributes);

        $validator = $this->factory->make($this->attributes, $rules, $this->messages);

        if ($validator->fails()) {
            /** @var array<string,array<string>> $errors */
            $errors = $validator->errors()->toArray();
            $this->errors = $errors;
            throw new ValidationException($this->errors);
        }

        /** @var array<string,mixed> */
        $validated = $validator->validated();

        return $validated;
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
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private static function autoCastAttributes(DtoInspector $inspector, array $attributes): array
    {
        $reflection = new ReflectionClass($inspector->getReflection()->getName());
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
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
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     *
     * @throws Exception
     */
    private static function tryCastParameter(array $attributes, ReflectionParameter $param): array
    {
        $name = $param->getName();
        $type = $param->getType();

        if (! isset($attributes[$name]) || ! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return $attributes;
        }

        $typeName = $type->getName();
        if ($attributes[$name] instanceof $typeName) {
            return $attributes;
        }

        $attributes[$name] = self::castValue($attributes[$name], $typeName);

        return $attributes;
    }

    /**
     * @throws Exception
     */
    private static function castValue(mixed $value, string $type): mixed
    {
        // Handle backed enums
        if (enum_exists($type)) {
            $reflectionEnum = new ReflectionEnum($type);
            if ($reflectionEnum->isBacked() && is_string($value)) {
                /** @var class-string<BackedEnum> $type */
                return $type::from($value);
            }
        }

        // Handle datetime types
        if (is_string($value)) {
            return match ($type) {
                Carbon::class => Carbon::parse($value),
                CarbonImmutable::class => CarbonImmutable::parse($value),
                DateTimeImmutable::class => new DateTimeImmutable($value),
                DateTime::class => new DateTime($value),
                default => $value
            };
        }

        return $value;
    }
}
