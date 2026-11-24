<?php

declare(strict_types=1);

namespace Axiom\DataForge\Validation;

use Axiom\DataForge\Exceptions\DataForgeException;
use Axiom\DataForge\Exceptions\InspectionException;
use Axiom\DataForge\Exceptions\ValidationException;
use Axiom\DataForge\Hydration\DtoHydrator;
use Axiom\DataForge\Input\ErrorKeyMapper;
use Axiom\DataForge\Input\InputMapper;
use Axiom\DataForge\Input\UnknownInputKeyValidator;
use Axiom\DataForge\Schema\DtoSchema;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
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
    private const RAW_SHAPE_RULES = [
        'array',
        'list',
        'required_array_keys',
    ];

    /** @var array<string,array<int,RuleValue>> */
    private array $rules = [];

    /** @var array<string,string> */
    private array $messages = [];

    private ValidationFactory $factory;

    /**
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     */
    private function __construct(
        private readonly DtoSchema $schema,
        private readonly array $attributes,
        private readonly bool $rejectsUnknownInputKeys,
        private readonly bool $isNested,
    ) {
        $this->factory = self::defaultValidationFactory();
    }

    /**
     * Create a validator for a root DTO. It checks unknown input keys and
     * reports errors with the caller's input keys.
     *
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     */
    public static function from(DtoSchema $schema, array $attributes, bool $rejectsUnknownInputKeys = false): self
    {
        return new self($schema, $attributes, $rejectsUnknownInputKeys, isNested: false);
    }

    /**
     * Create a validator for a nested DTO. Nested validators keep their error
     * keys in canonical form so the outermost (root) validator can translate
     * the fully-qualified path to input keys exactly once.
     *
     * @param  DtoSchema<object>  $schema
     * @param  array<string,mixed>  $attributes
     */
    public static function nested(DtoSchema $schema, array $attributes): self
    {
        return new self($schema, $attributes, rejectsUnknownInputKeys: false, isNested: true);
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
            $this->messages[$field] = $message;
        }

        return $this;
    }

    /**
     * @return array<string,mixed>
     *
     * @throws DataForgeException
     */
    public function validate(): array
    {
        if (! $this->isNested) {
            UnknownInputKeyValidator::assertNoUnknownKeys($this->schema, $this->attributes, $this->rejectsUnknownInputKeys);
        }

        $attributes = InputMapper::normalize($this->schema, $this->attributes);

        if ($this->isNested) {
            return $this->runValidation($attributes);
        }

        try {
            return $this->runValidation($attributes);
        } catch (ValidationException $e) {
            throw new ValidationException(ErrorKeyMapper::toInputKeys($this->schema, $e->errors));
        }
    }

    private static function defaultValidationFactory(): ValidationFactory
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'validation', self::defaultValidationMessages());

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
        // illuminate/translation ships these messages next to ArrayLoader
        // in every supported version (10.48 through 13).
        /** @var string $fileName */
        $fileName = (new ReflectionClass(ArrayLoader::class))->getFileName();

        /** @var array<string,mixed> $messages */
        $messages = require dirname($fileName).'/lang/en/validation.php';

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
     * Shape rules (`array`, `list`, `required_array_keys`) on top-level fields,
     * checked against the raw input before projection and hydration.
     *
     * @param  array<string,array<int,RuleValue>>  $rules
     * @return array<string,array<int,string>>
     */
    private static function rawShapeRules(array $rules): array
    {
        $rawShapeRules = [];

        foreach ($rules as $field => $fieldRules) {
            if (str_contains($field, '.')) {
                continue;
            }

            $shapeRules = [];
            foreach ($fieldRules as $rule) {
                if (! is_string($rule)) {
                    continue;
                }

                if (self::isRawShapeRule($rule)) {
                    $shapeRules[] = $rule;
                }
            }

            if ($shapeRules !== []) {
                $rawShapeRules[$field] = $shapeRules;
            }
        }

        return $rawShapeRules;
    }

    private static function isRawShapeRule(string $rule): bool
    {
        return in_array(self::ruleName($rule), self::RAW_SHAPE_RULES, true);
    }

    private static function ruleName(string $rule): string
    {
        return mb_strtolower(explode(':', $rule, 2)[0]);
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
        $rawShapeRules = self::rawShapeRules($this->rules);
        if ($rawShapeRules !== []) {
            $this->validateAttributes($attributes, $rawShapeRules);
        }

        $preHydrationAttributes = ValidationProjector::project($this->schema, $attributes);
        $preHydrationRules = DtoRuleBuilder::build(
            $this->schema,
            $this->rules,
            $preHydrationAttributes,
            includeTypeRules: false
        );
        $this->validateAttributes($preHydrationAttributes, $preHydrationRules);

        $hydratedAttributes = DtoHydrator::hydrate($this->schema, $attributes, $this->rules, $this->messages);
        $postHydrationRules = DtoRuleBuilder::build(
            $this->schema,
            [],
            $hydratedAttributes,
            includeAttributeRules: false
        );

        return $this->validateAttributes($hydratedAttributes, $postHydrationRules);
    }

    /**
     * Render `:attribute` in messages from the external input key for mapped
     * fields, so the message text matches the error key the caller sees.
     *
     * @return array<string,string>
     *
     * @throws InspectionException
     */
    private function displayAttributes(): array
    {
        $display = [];
        foreach ($this->schema->keyMap() as $property => $inputKey) {
            if ($property !== $inputKey) {
                $display[$property] = str_replace('_', ' ', Str::snake($inputKey));
            }
        }

        return $display;
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
        $validator = $this->factory->make($attributes, $rules, $this->messages, $this->displayAttributes());

        if ($validator->fails()) {
            /** @var array<string,array<int,string>> $errors */
            $errors = $validator->errors()->toArray();

            throw new ValidationException($errors);
        }

        return self::stringKeyedValidated($validator->validated());
    }
}
