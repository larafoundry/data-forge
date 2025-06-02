<?php

declare(strict_types=1);

namespace Axiom\DataForge\Concerns;

use Axiom\DataForge\Core\ContainerHelper;
use Axiom\DataForge\Core\DtoInspector;
use Axiom\DataForge\Core\FactoryManager;
use Axiom\DataForge\Core\Validator;
use Axiom\DataForge\Exceptions\ValidationException;
use ReflectionException;
use Throwable;

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
        $className = static::class;
        $inspector = new DtoInspector($className);

        $validator = Validator::from($inspector, $attributes)
            ->withRules(static::rules() ?: [])
            ->withMessages((static::messages() ?: []));

        $validatedData = $validator->validate();

        return ContainerHelper::makeInstance($className, $validatedData);
    }

    /**
     * Create a factory for this DTO
     *
     * @return FactoryManager<static>
     */
    public static function factory(): FactoryManager
    {
        return FactoryManager::from(static::class);
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
     * @return array<string, string|array>
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
}
