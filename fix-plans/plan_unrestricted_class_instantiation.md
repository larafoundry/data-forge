# Fix Plan: Unrestricted Class Instantiation

**Files Affected**: `src/Core/ContainerHelper.php`, `src/Core/RandomDataGenerator.php`, potentially a new configuration mechanism or service.
**Validated Issue Score**: 9/10

## 1. Problem Identification

`ContainerHelper::makeInstance()` and, by extension, `RandomDataGenerator` (which uses `FactoryManager` that in turn uses `ContainerHelper`, and also `RandomDataGenerator::createPlaceholder()`) can instantiate arbitrary classes based on a string input (`$class` or `$className`). This poses a significant security risk (Object Injection) if an attacker can control this class string, as they could instantiate unintended classes leading to various vulnerabilities including remote code execution.

## 2. Proposed Solution

Implement a mechanism to restrict which classes can be instantiated by the library. A configurable whitelist of allowed classes or namespaces is the most robust approach.

1.  **Configuration Mechanism**: Introduce a way for users to configure allowed classes/namespaces. This could be via a static configuration class, a setter method on a central service, or passed during initial setup.

    ```php
    // Example: Static Configuration Class
    namespace Ws\DataBridge\Core;

    class DataBridgeConfig
    {
        /** @var array<string> Prefixes of allowed namespaces */
        private static array $allowedNamespaces = [];
        /** @var array<class-string> Specific allowed classes */
        private static array $allowedClasses = [];

        public static function addAllowedNamespace(string $namespacePrefix): void
        {
            self::$allowedNamespaces[] = rtrim($namespacePrefix, '\\') . '\\';
        }

        public static function addAllowedClass(string $className): void
        {
            self::$allowedClasses[] = $className;
        }

        public static function isClassAllowed(string $className): bool
        {
            if (empty(self::$allowedNamespaces) && empty(self::$allowedClasses)) {
                // Default behavior: if nothing is configured, allow all for backward compatibility (during transition)
                // OR, for stricter security, default to DENY ALL if not configured.
                // This decision is critical and should be documented. For max security, default deny is better.
                // Let's assume default DENY for this plan if not configured.
                return false; // Or throw new ConfigurationException("No classes/namespaces configured as allowed.");
            }

            if (in_array($className, self::$allowedClasses, true)) {
                return true;
            }

            foreach (self::$allowedNamespaces as $prefix) {
                if (str_starts_with($className, $prefix)) {
                    return true;
                }
            }
            return false;
        }

        public static function clearAllowed(): void // For testing
        {
            self::$allowedNamespaces = [];
            self::$allowedClasses = [];
        }
    }
    ```
    *Important Decision*: How to behave if no allow-list is configured? Default to deny all (more secure) or allow all (less secure, easier initial adoption/backward compatibility). For a security feature, **default deny is strongly recommended.** Users *must* configure what they want to allow.

2.  **Integrate Check in `ContainerHelper::makeInstance`**:

    ```php
    // In ContainerHelper.php
    use Ws\DataBridge\Core\DataBridgeConfig; // Or your chosen config access method
    use Ws\DataBridge\Exceptions\SecurityViolationException; // New Exception

    // Potentially create a new SecurityViolationException extends DataBridgeException

    public static function makeInstance(string $class, array $data)
    {
        if (!DataBridgeConfig::isClassAllowed($class)) {
            throw new SecurityViolationException("Class '$class' is not allowed for instantiation.");
        }

        if (!class_exists($class)) { // This check should ideally come AFTER the allow-list check
            throw new InvalidArgumentException("Class $class not found");
        }

        // ... rest of the method
    }
    ```

3.  **Integrate Check in `RandomDataGenerator::createPlaceholder`** (if it directly instantiates):

    ```php
    // In RandomDataGenerator.php
    private function createPlaceholder(string $className)
    {
        // Potentially add a check here too if $reflection->newInstance() is used directly
        if (!DataBridgeConfig::isClassAllowed($className)) {
            throw new SecurityViolationException("Class '$className' is not allowed for placeholder instantiation.");
        }
        // ... rest of the method, including call to ReflectionCache::getClass($className)
    }
    ```

4.  **New Exception Type**:
    ```php
    // src/Exceptions/SecurityViolationException.php
    namespace Ws\DataBridge\Exceptions;

    class SecurityViolationException extends DataBridgeException {}
    ```

## 3. Justification

*   **Mitigates Object Injection**: This is the primary goal. By restricting instantiation to a known set of classes/namespaces, the risk of an attacker instantiating malicious or unintended classes is drastically reduced.
*   **Configurable Security**: Allows users of the library to define their own security boundaries based on which DTOs they expect to use.
*   **Explicit Control**: Forces an explicit decision about what classes are safe to handle, improving overall security posture.

## 4. Implementation Steps

1.  **Define `SecurityViolationException`**: Create the new exception class.
2.  **Implement `DataBridgeConfig` (or chosen configuration mechanism)**:
    *   Create `DataBridgeConfig.php` with methods for adding allowed namespaces/classes and checking if a class is allowed. Strongly consider the default behavior if nothing is configured (default deny is best).
3.  **Modify `ContainerHelper::makeInstance`**:
    *   Add the call to `DataBridgeConfig::isClassAllowed($class)` at the beginning of the method.
    *   Throw `SecurityViolationException` if not allowed.
4.  **Modify `RandomDataGenerator::createPlaceholder`**:
    *   If this method still contains logic like `$reflection->newInstance()` after other refactorings, add a similar check using `DataBridgeConfig::isClassAllowed($className)`.
5.  **Documentation**: Crucially document this new security feature:
    *   How to configure the allowed classes/namespaces.
    *   The default behavior if not configured (e.g., deny all).
    *   The new `SecurityViolationException`.
6.  **Testing**:
    *   Unit tests for `DataBridgeConfig`: test adding namespaces/classes, checking allowed/disallowed classes, edge cases (empty config, overlapping namespaces etc.).
    *   Unit tests for `ContainerHelper::makeInstance`: verify it throws `SecurityViolationException` for disallowed classes and proceeds for allowed ones.
    *   Test `RandomDataGenerator` similarly if direct instantiation checks are added there.
    *   Ensure existing library tests pass when appropriate classes are added to the allow-list during test setup.

## 5. Potential Risks and Mitigation

*   **Breaking Change / User Burden**: Users *must* now configure this for the library to work (if default deny is chosen). This is a breaking change.
    *   **Mitigation**: Clear and prominent documentation. Provide examples. Explain the security rationale. For a major version update, this is an acceptable and important change.
*   **Configuration Complexity**: If the list of DTOs is very large and diverse, configuration might seem tedious.
    *   **Mitigation**: Allowing namespace prefixes greatly simplifies configuration for projects with well-structured DTOs. Emphasize this in documentation.
*   **Overly Restrictive by Default**: If default deny is chosen and users miss the documentation, the library will seem broken.
    *   **Mitigation**: The `SecurityViolationException` should be very clear in its message, guiding the user towards the configuration.

This is a critical security enhancement and should be prioritized. 