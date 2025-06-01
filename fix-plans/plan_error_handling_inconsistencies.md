# Fix Plan: Error Handling Inconsistencies

**Files Affected**: All core files (`DtoInspector.php`, `RandomDataGenerator.php`, `FactoryManager.php`, `Validator.php`, `DtoRuleBuilder.php`, `ContainerHelper.php`), and potentially a new Exceptions directory/namespace.
**Validated Issue Score**: 7/10

## 1. Problem Identification

The library currently uses a mix of standard PHP exceptions (`InvalidArgumentException`, `RuntimeException`, `Exception`) and a custom `ValidationException`. Additionally, `Throwable` is caught in some places, which can mask specific error types and make debugging harder. This inconsistency makes it difficult for users of the library to anticipate and handle errors effectively.

## 2. Proposed Solution

Define a clear and consistent exception hierarchy for the Data Bridge library. This involves creating a base library exception and specific exceptions for different error categories.

1.  **Define a Base Exception**: Create a base exception for the library, e.g., `Ws\DataBridge\Exceptions\DataBridgeException.php`.
    ```php
    // src/Exceptions/DataBridgeException.php
    namespace Ws\DataBridge\Exceptions;

    class DataBridgeException extends \Exception {}
    ```

2.  **Create Specific Exception Types**: Create more specific exceptions that extend `DataBridgeException` (or standard PHP exceptions where appropriate, but ensure they are part of the documented API).
    *   `InvalidArgumentException` (standard PHP): Can continue to be used for invalid arguments passed to methods. Ensure it's consistently used for this purpose.
    *   `ReflectionRelatedException extends DataBridgeException`: For errors specifically arising from reflection operations (e.g., class not found, property not accessible if not using a standard ReflectionException).
        ```php
        // src/Exceptions/ReflectionRelatedException.php
        namespace Ws\DataBridge\Exceptions;

        class ReflectionRelatedException extends DataBridgeException {}
        ```
    *   `HydrationException extends DataBridgeException` (or `InstantiationException`): For errors during object instantiation or data hydration by `ContainerHelper` or `FactoryManager` (e.g., missing required parameters not caught by validation, instantiation failures).
        ```php
        // src/Exceptions/HydrationException.php
        namespace Ws\DataBridge\Exceptions;

        class HydrationException extends DataBridgeException {}
        ```
    *   `GenerationException extends DataBridgeException`: For errors specific to `RandomDataGenerator` (e.g., cannot generate data for a type, placeholder creation failure if not a `ReflectionRelatedException`).
        ```php
        // src/Exceptions/GenerationException.php
        namespace Ws\DataBridge\Exceptions;

        class GenerationException extends DataBridgeException {}
        ```
    *   `ValidationException extends DataBridgeException`: The existing `ValidationException` should be updated to extend `DataBridgeException`. Its structure for holding multiple validation messages should be preserved.
        ```php
        // src/Exceptions/ValidationException.php (Modify existing)
        namespace Ws\DataBridge\Exceptions;

        // Ensure it extends DataBridgeException
        class ValidationException extends DataBridgeException
        {
            protected array $errors;

            public function __construct(array $errors, string $message = "Validation failed", int $code = 0, ?\Throwable $previous = null)
            {
                $this->errors = $errors;
                parent::__construct($message, $code, $previous);
            }

            public function getErrors(): array
            {
                return $this->errors;
            }
        }
        ```

3.  **Refactor Core Classes to Use New Exceptions**:
    *   Replace generic `RuntimeException` and `Exception` throws with more specific exceptions from the new hierarchy.
    *   Avoid catching generic `Throwable` where possible. Instead, catch more specific exceptions. If `Throwable` must be caught (e.g., at the very edge of a public API method to ensure a response), it should be logged and potentially re-thrown as a `DataBridgeException` or a more specific library exception, preserving the original exception as the `previous` exception.
    *   **Example - `RandomDataGenerator::generate()`**:
        ```php
        // Might throw GenerationException for unsupported types
        // Might throw ReflectionRelatedException if ReflectionCache or direct reflection fails
        ```
    *   **Example - `ContainerHelper::makeInstance()`**:
        ```php
        // Might throw InvalidArgumentException for missing class or parameter
        // Might throw ReflectionRelatedException for reflection issues
        // Might throw HydrationException for instantiation issues
        ```

4.  **Update PHPDocs**: All methods that can throw exceptions must have their `@throws` tags updated to reflect the new, specific exception types they may throw.

## 3. Justification

*   **Clarity and Predictability**: Consumers of the library can catch specific exceptions and handle them appropriately, rather than relying on generic `Exception` or message parsing.
*   **Improved Debugging**: Specific exception types provide better context about what went wrong.
*   **API Contract**: A well-defined exception hierarchy is an important part of a library's API contract.
*   **Reduced `Throwable` Catching**: Encourages more granular error handling.

## 4. Implementation Steps

1.  **Create New Exception Classes**: Create the `DataBridgeException.php` base class and other specific exception classes (e.g., `ReflectionRelatedException.php`, `HydrationException.php`, `GenerationException.php`) in a new `src/Exceptions` directory.
2.  **Modify `ValidationException.php`**: Update it to extend `DataBridgeException` and ensure its constructor and methods are preserved/adjusted.
3.  **Systematic Refactoring (File by File)**:
    *   **`DtoInspector.php`**: Review `new ReflectionClass()` calls. If these are now via `ReflectionCache` which throws `ReflectionException`, decide if `DtoInspector` should catch and re-throw as `ReflectionRelatedException` or let `ReflectionException` propagate (documenting it).
    *   **`RandomDataGenerator.php`**: Replace `RuntimeException` with `GenerationException` or `ReflectionRelatedException` as appropriate. Review `Throwable` catches.
    *   **`FactoryManager.php`**: Review throws and catches. Use `HydrationException`, `ReflectionRelatedException`, `InvalidArgumentException`.
    *   **`Validator.php`**: Ensure `ValidationException` is correctly used. Review other potential exceptions.
    *   **`DtoRuleBuilder.php`**: Primarily throws `InvalidArgumentException`, which is likely fine. Review if any other cases could arise.
    *   **`ContainerHelper.php`**: Replace `InvalidArgumentException` where more specific exceptions like `HydrationException` or `ReflectionRelatedException` are more appropriate.
4.  **Update All PHPDoc `@throws` Tags**: This is crucial. Every public method should accurately document the specific exceptions it might throw from the new hierarchy.
5.  **Testing**:
    *   Write unit tests for any new logic in the exception classes themselves (e.g., if `ValidationException` constructor changes significantly).
    *   Review existing tests. Many tests that expect generic exceptions will need to be updated to expect the new specific exception types.
    *   Add new tests to verify that specific error conditions throw the correct new exception types.

## 5. Potential Risks and Mitigation

*   **Breaking Changes**: This is a significant breaking change for users of the library, as their `catch` blocks will need to be updated.
    *   **Mitigation**: Clearly document this change in the release notes. Provide guidance on migrating. This change is for a major version bump (e.g., v1.x to v2.0).
*   **Missing Cases**: Might miss some places where generic exceptions are thrown or caught.
    *   **Mitigation**: Thorough code review and testing are essential. Static analysis tools (like PHPStan with strict rules) can help identify unhandled or incorrectly documented exceptions.
*   **Overly Granular Exceptions**: Creating too many highly specific exceptions can also be cumbersome.
    *   **Mitigation**: Strive for a balance. The proposed set (`DataBridgeException`, `ReflectionRelatedException`, `HydrationException`, `GenerationException`, `ValidationException`, plus standard `InvalidArgumentException`) seems like a reasonable starting point.

This refactoring will significantly improve the robustness and usability of the library from an error-handling perspective. 