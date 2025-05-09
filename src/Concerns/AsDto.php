<?php

declare(strict_types=1);

namespace Ws\DataBridge\Concerns;

use ReflectionException;
use Ws\DataBridge\Core\ContainerHelper;
use Ws\DataBridge\Core\DtoInspector;
use Ws\DataBridge\Core\Validator;
use Ws\DataBridge\Exceptions\ValidationException;

trait AsDto
{
    /**
     * @template T of object
     * @param array<string, mixed> $attributes
     * @return static
     * @throws ValidationException|ReflectionException
     */
    public static function fromArray(array $attributes): static
    {
        $className = static::class;
        $inspector = new DtoInspector($className);

        $validator = Validator::from($inspector, $attributes)
            ->withRules(static::rules() ?: [])
            ->withMessages((static::messages() ?: []));

        $validatedData = $validator->validate();

        return ContainerHelper::makeInstance($className, $validatedData);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [];
    }
}
