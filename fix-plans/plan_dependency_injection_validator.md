# Fix Plan: Dependency Injection for Validator

**File Affected**: `src/Core/Validator.php`, potentially a new interface and a new service provider/factory for the validation component.
**Validated Issue Score**: 7/10

## 1. Problem Identification

`Validator.php` has a hard dependency on Laravel's validation components (`Illuminate\Validation\Factory`, `Illuminate\Translation\Translator`, `Illuminate\Translation\ArrayLoader`, `Illuminate\Container\Container`). This is evident in its constructor where it directly instantiates `ValidationFactory`:

```php
// Validator::__construct
$this->factory = $factory ?? new ValidationFactory(
    new Translator(new ArrayLoader(), 'en'),
    new Container()
);
```

This tight coupling makes it difficult to:
*   Use a different validation library.
*   Mock the validation mechanism for testing `Validator.php` in isolation.
*   Control the setup of the validation factory (e.g., translator, container) from outside.

## 2. Proposed Solution

Abstract the validation mechanism behind an interface and inject an implementation of this interface into the `Validator`.

1.  **Define a Validation Service Interface**: Create an interface that defines the contract for a validation service this library needs. This interface would wrap the essential functionality currently used from `Illuminate\Validation\Factory`.

    ```php
    // src/Contracts/ValidationServiceInterface.php (New directory/namespace)
    namespace Ws\DataBridge\Contracts;

    interface ValidationServiceInterface
    {
        /**
         * Create a new validator instance.
         *
         * @param  array<string, mixed>  $data
         * @param  array<string, string|array<int,string>>  $rules
         * @param  array<string, string>  $messages
         * @param  array<string, string>  $customAttributes
         * @return ValidatorInstanceInterface // Another new interface for the validator instance
         */
        public function make(
            array $data,
            array $rules,
            array $messages = [],
            array $customAttributes = []
        ): ValidatorInstanceInterface;
    }

    // src/Contracts/ValidatorInstanceInterface.php
    namespace Ws\DataBridge\Contracts;

    interface ValidatorInstanceInterface
    {
        public function fails(): bool;
        public function errors(): array; // Or a more specific MessageBagInterface if desired
        /** @return array<string,mixed> */
        public function validated(): array;
    }
    ```

2.  **Create an Adapter/Implementation for Illuminate Validator**: This class will implement `ValidationServiceInterface` and `ValidatorInstanceInterface` and use the Illuminate components internally.

    ```php
    // src/Adapters/IlluminateValidationService.php (New directory/namespace)
    namespace Ws\DataBridge\Adapters;

    use Illuminate\Validation\Factory as IlluminateValidationFactory;
    use Ws\DataBridge\Contracts\ValidationServiceInterface;
    use Ws\DataBridge\Contracts\ValidatorInstanceInterface;

    class IlluminateValidationService implements ValidationServiceInterface
    {
        private IlluminateValidationFactory $factory;

        public function __construct(IlluminateValidationFactory $factory)
        {
            $this->factory = $factory;
        }

        public function make(
            array $data,
            array $rules,
            array $messages = [],
            array $customAttributes = []
        ): ValidatorInstanceInterface {
            return new IlluminateValidatorAdapter(
                $this->factory->make($data, $rules, $messages, $customAttributes)
            );
        }
    }

    // src/Adapters/IlluminateValidatorAdapter.php
    namespace Ws\DataBridge\Adapters;

    use Illuminate\Validation\Validator as IlluminateValidator;
    use Ws\DataBridge\Contracts\ValidatorInstanceInterface;

    class IlluminateValidatorAdapter implements ValidatorInstanceInterface
    {
        private IlluminateValidator $validator;

        public function __construct(IlluminateValidator $validator)
        {
            $this->validator = $validator;
        }

        public function fails(): bool
        {
            return $this->validator->fails();
        }

        public function errors(): array
        {
            // The toArray() method exists on MessageBag
            return $this->validator->errors()->toArray();
        }

        public function validated(): array
        {
            return $this->validator->validated();
        }
    }
    ```

3.  **Refactor `Validator` to Accept `ValidationServiceInterface`**: Modify `Validator::__construct` to accept an instance of `ValidationServiceInterface`.

    ```php
    // In src/Core/Validator.php
    use Ws\DataBridge\Contracts\ValidationServiceInterface;
    use Ws\DataBridge\Exceptions\ValidationException; // Assuming this still exists
    // ... other uses ...

    final class Validator
    {
        // ... properties ...
        private ValidationServiceInterface $validationService; // Changed from IlluminateValidationFactory

        public function __construct(
            private readonly DtoInspector $inspector,
            private readonly array $attributes,
            ValidationServiceInterface $validationService // Injected dependency
        ) {
            // $this->factory replaced by $this->validationService
            $this->validationService = $validationService;
        }

        // The static `from` method will need to be adjusted or users will have to instantiate Validator directly.
        // Option 1: `from` method now requires ValidationServiceInterface
        public static function from(
            DtoInspector $inspector,
            array $attributes,
            ValidationServiceInterface $validationService // Pass it in
        ): self {
            $attributes = self::autoCastAttributes($inspector, $attributes);
            return new self($inspector, $attributes, $validationService);
        }

        // Option 2: Remove `from` or provide a default way to get ValidationServiceInterface (e.g. a global factory/DI container access)
        // For library simplicity, requiring it in `from` or constructor is clearer.

        public function validate(): array
        {
            // ... build $mappedRules, $mappedMessages ...

            // Use the injected service
            $validatorInstance = $this->validationService->make($this->attributes, $mappedRules, $mappedMessages);

            if ($validatorInstance->fails()) {
                $this->errors = $validatorInstance->errors(); // Assuming errors() returns array<string,array<string>>
                throw new ValidationException($this->errors);
            }
            /** @var array<string,mixed> $validated */
            $validated = $validatorInstance->validated();
            // ... rest of mapping validated data ...
            return $mappedValidated;
        }
        // ...
    }
    ```

4.  **Library Usage**: Users of the library will now need to provide an implementation of `ValidationServiceInterface` when creating a `Validator` instance. The library can ship with `IlluminateValidationService` as the default/recommended one if Laravel components are available.

## 3. Justification

*   **Decoupling**: Removes the hard dependency on Illuminate components, making `Validator.php` and the library core more framework-agnostic.
*   **Testability**: Allows `Validator.php` logic to be tested in isolation by mocking `ValidationServiceInterface`.
*   **Flexibility**: Users can potentially plug in other validation libraries by creating their own adapters for `ValidationServiceInterface`.
*   **Clearer Dependencies**: Makes the dependency on a validation mechanism explicit through the constructor.

## 4. Implementation Steps

1.  **Define Interfaces**: Create `ValidationServiceInterface.php` and `ValidatorInstanceInterface.php` in a `src/Contracts` directory.
2.  **Create Adapters**: Implement `IlluminateValidationService.php` and `IlluminateValidatorAdapter.php` in a `src/Adapters` (or similar) directory.
3.  **Refactor `Validator.php`**:
    *   Change the constructor to accept `ValidationServiceInterface`.
    *   Update the `validate()` method to use the injected service and the new `ValidatorInstanceInterface`.
    *   Decide on the strategy for the static `from()` method (e.g., require `ValidationServiceInterface` as a parameter).
4.  **Update Instantiation Points**: Any code that directly creates `Validator` instances will need to be updated to provide the `ValidationServiceInterface` implementation. This includes `FactoryManager::make()` and `FactoryManager::random()`.
    *   `FactoryManager` might also need to accept `ValidationServiceInterface` in its constructor or have a way to access/create it.
5.  **Documentation**: Update documentation to explain how to provide/configure the `ValidationServiceInterface`.
6.  **Testing**:
    *   Write unit tests for the new adapter classes (`IlluminateValidationService`, `IlluminateValidatorAdapter`).
    *   Update unit tests for `Validator.php`. Mock `ValidationServiceInterface` and `ValidatorInstanceInterface` to test `Validator`'s own logic (rule building, error mapping, etc.) independently of the actual validation engine.
    *   Ensure integration tests still pass by providing the `IlluminateValidationService` (with a properly configured `IlluminateValidationFactory`) during test setup.

## 5. Potential Risks and Mitigation

*   **Increased Complexity for Users**: Users now need to understand and provide the `ValidationServiceInterface`. This is a breaking change.
    *   **Mitigation**: Provide `IlluminateValidationService` as a ready-to-use implementation. Document clearly how to set it up (e.g., how to create an `IlluminateValidationFactory`). Offer a simple factory method or default setup if Laravel components are common in the target environment.
*   **Interface Design**: The `ValidationServiceInterface` and `ValidatorInstanceInterface` need to be carefully designed to be generic enough yet cover the library's needs.
    *   **Mitigation**: Start with the exact methods currently used from the Illuminate components. Generalize only if necessary. The current proposal is closely mapped.
*   **Dependency Management for Adapter**: The `IlluminateValidationService` still depends on Illuminate components. Users will need to ensure these are available if they use this adapter.
    *   **Mitigation**: Clearly document these peer dependencies if using the provided adapter. Make the core library itself not directly require `illuminate/validation` in `composer.json` (it becomes a suggestion or a requirement of the adapter).

This change significantly improves the architecture and flexibility of the validation component. 