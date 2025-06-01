# Fix Plan: Input Sanitization Missing (for Class Names)

**Files Affected**: `src/Core/DtoInspector.php`, `src/Core/ContainerHelper.php`, `src/Core/RandomDataGenerator.php`, and any other place a class name string is taken as input for reflection.
**Validated Issue Score**: 7/10

## 1. Problem Identification

Class name strings, typically received as parameters (e.g., `$class` in `DtoInspector::__construct`, `ContainerHelper::makeInstance`, `$className` in `RandomDataGenerator`), are used directly in reflection operations like `new ReflectionClass($className)`. While `ReflectionClass` will throw an exception if the class name is syntactically invalid or the class doesn't exist, there's no preliminary sanitization or validation of the class name string itself. This primarily complements the "Unrestricted Class Instantiation" fix: even if a class is on an allow-list, its name should still be validated for basic well-formedness.

## 2. Proposed Solution

Implement a validation step for class name strings before they are used in reflection operations or checked against an allow-list.

1.  **Create a Validation Utility/Function**: A helper function, possibly in `DataBridgeConfig` or a new `ValidationUtils` class, to check if a string is a syntactically valid PHP class name.

    ```php
    // Example: In a new ValidationUtils class or DataBridgeConfig
    public static function isValidPhpClassName(string $className): bool
    {
        // A basic check: Namespaces and class name parts should be valid PHP labels.
        // PHP labels start with a letter or underscore, followed by letters, numbers, or underscore.
        // Fully qualified class names can contain backslashes.
        if (empty($className)) {
            return false;
        }
        // Remove leading backslash for consistency if present
        $normalizedClassName = ltrim($className, '\\');

        // Check for invalid characters - basic version
        // A more robust regex could be used, but this catches common issues.
        if (preg_match('/[^\\\\a-zA-Z0-9_]/ ', $normalizedClassName)) {
            return false; // Contains characters not allowed in FQCNs
        }

        // Ensure parts separated by \ are valid labels
        $parts = explode('\\', $normalizedClassName);
        if (empty($parts)) {
            return false;
        }
        foreach ($parts as $part) {
            if (empty($part) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
                return false; // Invalid label part (e.g., starts with number, empty part)
            }
        }
        return true;
    }
    ```
    *Note*: A perfect regex for PHP class names can be complex due to reserved words and other nuances. The goal here is to catch common malformations, not to perfectly replicate PHP's own parser for class names. PHP's `class_exists` or `ReflectionClass` will be the ultimate arbiter of whether the class is loadable.

2.  **Integrate Validation**: Call this validation function at the entry points where class strings are received.

    *   **`DtoInspector::__construct(readonly string $class)`**
    *   **`ContainerHelper::makeInstance(string $class, array $data)`**
    *   **`RandomDataGenerator` methods taking `className` if it leads to reflection.**
    *   **`FactoryManager::from(string $class)`**

    ```php
    // Example in FactoryManager::from()
    use Ws\DataBridge\Core\ValidationUtils; // Assuming new location
    use Ws\DataBridge\Exceptions\InvalidInputException; // New or use InvalidArgumentException

    // Potentially create InvalidInputException extends DataBridgeException

    public static function from(string $class): self
    {
        if (!ValidationUtils::isValidPhpClassName($class)) {
            throw new InvalidInputException("Invalid class name format provided: '$class'"); // Or InvalidArgumentException
        }
        // If using an allow-list, that check might come before or after this basic syntax check.
        // DataBridgeConfig::isClassAllowed($class) should also be called.
        return new self($class);
    }
    ```
    This check should occur *before* the `DataBridgeConfig::isClassAllowed()` check, or as part of it, to ensure malformed names are rejected early.

3.  **New/Used Exception Type**:
    *   Use standard `InvalidArgumentException` or create a more specific `InvalidInputException extends DataBridgeException`.

## 3. Justification

*   **Defense in Depth**: Complements the allow-list mechanism. Ensures that even if a class name string bypasses other checks or if the allow-list is misconfigured, malformed strings are rejected.
*   **Early Failure**: Catches obviously invalid class name formats early, providing clearer error messages before hitting deeper reflection errors or potentially problematic `class_exists` autoloading side-effects with weird strings.
*   **Improved Robustness**: Makes the library more resilient to malformed inputs.

## 4. Implementation Steps

1.  **Implement `ValidationUtils::isValidPhpClassName()`** (or place it in `DataBridgeConfig`). Refine the validation logic/regex as needed for robustness.
2.  **Define `InvalidInputException`** (Optional): If a more specific exception than `InvalidArgumentException` is desired.
3.  **Integrate Validation Calls**: Add calls to `isValidPhpClassName()` in the constructors or methods of:
    *   `FactoryManager::from()`
    *   `DtoInspector::__construct()`
    *   `ContainerHelper::makeInstance()` (if class name isn't already validated by `FactoryManager` or `DtoInspector` which call it)
    *   Relevant methods in `RandomDataGenerator`.
    Throw `InvalidInputException` or `InvalidArgumentException` on failure.
4.  **Order of Checks**: Ensure this syntactic validation occurs before or alongside the `DataBridgeConfig::isClassAllowed()` check.
5.  **Documentation**: Document that class name inputs are validated for format.
6.  **Testing**:
    *   Unit tests for `isValidPhpClassName()` with various valid and invalid class name strings (including those with leading backslashes, invalid characters, empty parts).
    *   Update unit tests for the refactored methods to ensure they throw the correct exception for malformed class names.

## 5. Potential Risks and Mitigation

*   **Overly Strict Validation**: The `isValidPhpClassName` logic might be too strict and reject some valid (though perhaps obscure) class names.
    *   **Mitigation**: The regex/logic should be based on PHP's rules for identifiers and namespaces. Test thoroughly. The ultimate check is `class_exists` or `ReflectionClass`, so this is a preliminary filter.
*   **Performance**: Adding regex checks introduces some overhead.
    *   **Mitigation**: This check is usually at the beginning of an operation that will subsequently perform more expensive reflection. The overhead is likely negligible compared to the overall operation and the security/robustness gain.

This fix enhances the input validation of the library, contributing to its overall security and stability. 