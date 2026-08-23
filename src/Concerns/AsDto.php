<?php

declare(strict_types=1);

namespace Axiom\DataForge\Concerns;

use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Hydration\ContainerHelper;
use Axiom\DataForge\Schema\DtoClass;
use Axiom\DataForge\Schema\DtoInspector;
use Axiom\DataForge\Validation\DtoRuleBuilder;
use Axiom\DataForge\Validation\Validator;
use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use ReflectionException;
use stdClass;
use Throwable;

/**
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 */
trait AsDto
{
    /**
     * @template T of object
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException|ReflectionException|Throwable
     */
    public static function fromArray(array $attributes): static
    {
        return static::makeFromArray(
            $attributes,
            DtoClass::rejectsUnknownInputKeys(static::class)
        );
    }

    /**
     * @template T of object
     *
     * @throws ValidationException|ReflectionException|Throwable
     */
    public static function fromJson(string $json): static
    {
        try {
            $root = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $attributes = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationException([
                'json' => ['The JSON payload must be valid JSON.'],
            ]);
        }

        if (! $root instanceof stdClass) {
            throw new ValidationException([
                'json' => ['The JSON payload must decode to an object.'],
            ]);
        }

        return static::fromArray(self::stringKeyedRootArray(
            $attributes,
            'json',
            'The JSON payload must decode to an object.',
            'The JSON payload must decode to a string-keyed object.'
        ));
    }

    /**
     * @template T of object
     *
     * @param  Arrayable<array-key,mixed>  $attributes
     *
     * @throws ValidationException|ReflectionException|Throwable
     */
    public static function fromArrayable(Arrayable $attributes): static
    {
        return static::fromArray(self::stringKeyedRootArray(
            $attributes->toArray(),
            'attributes',
            'The arrayable payload must return an array.',
            'The arrayable payload must return a string-keyed array.'
        ));
    }

    /**
     * Define validation rules using Laravel validation syntax
     *
     * Example:
     * [
     *   'name' => 'required|string|min:3',
     *   'email' => 'required|email',
     * ]
     *
     * @return array<string, string|array<int, RuleValue>|RuleValue>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * Define custom error messages for validation rules
     *
     * Example:
     * [
     *   'name.required' => 'The name field is required.',
     *   'email.email' => 'Please provide a valid email address.',
     * ]
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [];
    }

    /**
     * @template T of object
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException|ReflectionException|Throwable
     */
    private static function makeFromArray(array $attributes, bool $rejectsUnknownInputKeys): static
    {
        $className = static::class;
        $inspector = new DtoInspector($className);

        $validator = Validator::from($inspector, $attributes, $rejectsUnknownInputKeys)
            ->withRules($inspector->getRules())
            ->withMessages($inspector->getMessages());

        $validatedData = $validator->validate();

        return ContainerHelper::makeInstance($className, $validatedData);
    }

    /**
     * @return array<string,mixed>
     *
     * @throws ValidationException
     */
    private static function stringKeyedRootArray(
        mixed $attributes,
        string $errorKey,
        string $notArrayMessage,
        string $notStringKeyedMessage
    ): array {
        if (! is_array($attributes)) {
            throw new ValidationException([
                $errorKey => [$notArrayMessage],
            ]);
        }

        $result = [];
        foreach ($attributes as $key => $value) {
            if (! is_string($key)) {
                throw new ValidationException([
                    $errorKey => [$notStringKeyedMessage],
                ]);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
