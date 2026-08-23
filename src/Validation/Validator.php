<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

use Axiom\DataForge\Exceptions\DataForgeException;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Hydration\DtoHydrator;
use Axiom\DataForge\Input\ErrorKeyMapper;
use Axiom\DataForge\Input\InputMapper;
use Axiom\DataForge\Input\UnknownInputKeyValidator;
use Axiom\DataForge\Schema\DtoInspector;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use ReflectionClass;

/**
 * @phpstan-import-type RuleValue from DtoRuleBuilder
 * @phpstan-import-type BuiltRule from DtoRuleBuilder
 */
final class Validator
{
    /** @var array<string,array<int,RuleValue>> */
    private array $rules = [];

    /** @var array<string,string> */
    private array $messages = [];

    /** @var array<string,array<int,string>> */
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
        private readonly bool $autoCastAfterValidation = false,
        private readonly bool $rejectsUnknownInputKeys = false,
        private readonly bool $checksUnknownInputKeys = true,
        private readonly bool $isNested = false,
    ) {
        $this->factory = $factory ?? self::defaultValidationFactory();
    }

    /**
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     *
     * @throws DataForgeException
     */
    public static function from(DtoInspector $inspector, array $attributes, bool $rejectsUnknownInputKeys = false): self
    {
        return new self(
            $inspector,
            $attributes,
            autoCastAfterValidation: true,
            rejectsUnknownInputKeys: $rejectsUnknownInputKeys
        );
    }

    /**
     * Create a validator for a nested DTO. Nested validators keep their error
     * keys in canonical form so the outermost (root) validator can translate
     * the fully-qualified path to input keys exactly once.
     *
     * @param  DtoInspector<object>  $inspector
     * @param  array<string,mixed>  $attributes
     */
    public static function nested(DtoInspector $inspector, array $attributes): self
    {
        return new self(
            $inspector,
            $attributes,
            autoCastAfterValidation: true,
            checksUnknownInputKeys: false,
            isNested: true
        );
    }

    /**
     * @param  array<string,string|array<int,mixed>|object>  $rules
     */
    public function withRules(array $rules): self
    {
        foreach ($rules as $field => $fieldRules) {
            if (! is_string($field)) {
                throw new InspectionException('Validation rule field names must be strings.');
            }

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
        foreach ($messages as $field => $message) {
            if (! is_string($field) || ! is_string($message)) {
                throw new InspectionException('Validation messages must be string-keyed strings.');
            }

            $this->messages[$field] = $message;
        }

        return $this;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string,array<int,string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string,array<int,RuleValue>>
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
     * @throws DataForgeException
     */
    public function validate(): array
    {
        try {
            if ($this->checksUnknownInputKeys) {
                UnknownInputKeyValidator::assertNoUnknownKeys(
                    $this->inspector,
                    $this->attributes,
                    $this->rejectsUnknownInputKeys
                );
            }

            $attributes = InputMapper::normalize($this->inspector, $this->attributes);
        } catch (UnknownInputKeyException $e) {
            // Unknown-key violations are already keyed in the caller's namespace.
            $this->errors = $e->errors;
            throw $e;
        }

        if ($this->isNested) {
            return $this->runValidation($attributes);
        }

        try {
            return $this->runValidation($attributes);
        } catch (ValidationException $e) {
            $this->errors = ErrorKeyMapper::toInputKeys($this->inspector, $e->errors);
            throw new ValidationException($this->errors);
        }
    }

    /**
     * @return array<string,mixed>|false
     *
     * @throws DataForgeException
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
     * @param  array<mixed,mixed>  $errors
     * @return array<string,array<int,string>>
     */
    private static function stringErrorBag(array $errors): array
    {
        $result = [];
        foreach ($errors as $field => $messages) {
            if (! is_string($field) || ! is_array($messages)) {
                continue;
            }

            $fieldMessages = [];
            foreach ($messages as $message) {
                if (is_string($message)) {
                    $fieldMessages[] = $message;
                }
            }

            $result[$field] = $fieldMessages;
        }

        return $result;
    }

    private static function defaultValidationFactory(): ValidationFactory
    {
        $loader = new ArrayLoader();
        $messages = self::defaultValidationMessages();

        if ($messages !== []) {
            $loader->addMessages('en', 'validation', $messages);
        }

        return new ValidationFactory(
            new Translator($loader, 'en'),
            new Container()
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function defaultValidationMessages(): array
    {
        $fileName = (new ReflectionClass(ArrayLoader::class))->getFileName();
        if (! is_string($fileName)) {
            return [];
        }

        $path = dirname($fileName).'/lang/en/validation.php';
        if (! is_file($path)) {
            return [];
        }

        $messages = require $path;
        if (! is_array($messages)) {
            return [];
        }

        /** @var array<string,mixed> $messages */
        return $messages;
    }

    /**
     * @param  array<mixed,mixed>  $validated
     * @return array<string,mixed>
     */
    private static function stringKeyedValidated(array $validated): array
    {
        $result = [];
        foreach ($validated as $field => $value) {
            if (is_string($field)) {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * Run the validation pipeline, producing canonical-keyed results and errors.
     *
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     *
     * @throws DataForgeException
     */
    private function runValidation(array $attributes): array
    {
        if (! $this->autoCastAfterValidation) {
            return $this->validateAttributes(
                $attributes,
                DtoRuleBuilder::build($this->inspector, $this->rules, $attributes)
            );
        }

        $rawShapeRules = RulePlanner::rawShapeRules($this->rules);
        if ($rawShapeRules !== []) {
            $this->validateAttributes($attributes, $rawShapeRules);
        }

        $preHydrationAttributes = ValidationProjector::project($this->inspector, $attributes);
        $preHydrationRules = DtoRuleBuilder::build(
            $this->inspector,
            $this->rules,
            $preHydrationAttributes,
            includeTypeRules: false
        );
        $this->validateAttributes($preHydrationAttributes, $preHydrationRules);

        $hydratedAttributes = DtoHydrator::hydrate($this->inspector, $attributes, $this->rules, $this->messages);
        $postHydrationRules = DtoRuleBuilder::build(
            $this->inspector,
            [],
            $hydratedAttributes,
            includeAttributeRules: false
        );

        return $this->validateAttributes($hydratedAttributes, $postHydrationRules);
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @param  array<string,array<int,BuiltRule>>  $rules
     * @return array<string,mixed>
     *
     * @throws ValidationException
     */
    private function validateAttributes(array $attributes, array $rules): array
    {
        $validator = $this->factory->make($attributes, $rules, $this->messages);

        if ($validator->fails()) {
            $this->errors = self::stringErrorBag($validator->errors()->toArray());
            throw new ValidationException($this->errors);
        }

        return self::stringKeyedValidated($validator->validated());
    }
}
