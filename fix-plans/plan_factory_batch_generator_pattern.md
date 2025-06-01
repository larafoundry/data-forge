# Fix Plan: Generator Pattern for Large Datasets in FactoryBatch

**File Affected**: `src/Core/FactoryBatch.php`
**Validated Issue Score**: 7/10

## 1. Problem Identification

The `make()` method in `FactoryBatch.php` (lines 99-116) currently creates all DTO instances in a loop and accumulates them in an array (`$result[] = $factory->make();`). This array is then returned. For a large `$batchCount`, this approach can consume a significant amount of memory as all objects are held in memory simultaneously.

## 2. Proposed Solution

Modify the `make()` method to return a PHP `Generator` using the `yield` keyword. This will allow objects to be created and yielded one by one, consuming memory for only one object (or a few, depending on how the generator is consumed) at a time during the iteration.

1.  **Change Return Type**: Update the PHPDoc and potentially the PHP return type hint (if PHP 7.1+ with generator return types is targeted, though PHPDoc is primary here for older versions or mixed typed code) for the `make()` method.
    *   PHPDoc: `@return \Generator<int, T>` or `@return iterable<int, T>` (iterable is more general).
    *   PHP return type: `:\Generator` or `iterable` (if supported by project's PHP version requirements).

2.  **Use `yield`**: Replace array accumulation with `yield`.

    ```php
    // In src/Core/FactoryBatch.php

    /**
     * Create the batch of objects lazily.
     *
     * @return \Generator<int, T>  // Or iterable<int, T>
     *
     * @throws ReflectionException|ValidationException|\Throwable // Add Throwable if FactoryManager::make() can throw it
     */
    public function make(): \Generator // Or iterable
    {
        // If FactoryManager itself needs ValidationServiceInterface (from another fix plan),
        // FactoryBatch might need to be aware of it or receive it to pass to FactoryManager.
        // This depends on how FactoryManager is refactored for DI.
        // For now, assume FactoryManager::from() works as is or is handled elsewhere.

        for ($i = 0; $i < $this->batchCount; $i++) {
            $factoryManager = FactoryManager::from($this->class)
                ->withValues($this->values);

            if ($this->shouldFillRandom) {
                $factoryManager->fillRandom();
            }

            // Yield each object as it's created
            yield $i => $factoryManager->make(); // Yield with key $i to maintain similar structure to array if needed
                                               // Or just: yield $factoryManager->make();
        }
    }
    ```

## 3. Justification

*   **Memory Efficiency**: This is the primary benefit. For large batches, memory usage will be drastically reduced as objects are generated on demand instead of all at once.
*   **Scalability**: Allows the creation of virtually an unlimited number of DTOs in a batch, constrained by processing time rather than memory.
*   **Composability**: Generators are iterable and can be easily chained with other operations or consumed in various ways.

## 4. Implementation Steps

1.  **Modify `FactoryBatch::make()` Method**:
    *   Change the PHPDoc `@return` tag to `\Generator<int, T>` or `iterable<int, T>`.
    *   If applicable, change the PHP return type hint to `:\Generator` or `:iterable`.
    *   Remove the `$result = [];` initialization and `return $result;`.
    *   Replace `$result[] = $factory->make();` with `yield $i => $factoryManager->make();` (or `yield $factoryManager->make();`).
2.  **Update Method Signature for Throws**: Review the `@throws` tag for `make()`. Since `FactoryManager::make()` can throw `Throwable` (as per its PHPDoc), `FactoryBatch::make()` should also declare it.
3.  **Review Consumer Code (if any within the project)**: If other parts of this library directly consume `FactoryBatch::make()` and expect an array, they will need to be updated (e.g., by using `iterator_to_array()` if the full array is strictly needed, or by iterating over the generator).
4.  **Documentation**: Clearly document that `make()` now returns a generator/iterable and explain the benefits and how to consume it (e.g., `foreach` loop, `iterator_to_array()`).
5.  **Testing**:
    *   Update unit tests for `FactoryBatch::make()`.
        *   Verify it returns a `Generator` object.
        *   Iterate over the generator and assert the correct number and type of objects are yielded.
        *   Test that objects are indeed generated lazily (this might require more complex tests, possibly involving mocks in `FactoryManager` to count instantiations before and during iteration).
    *   Ensure any integration tests that use `FactoryBatch` are updated to correctly consume the generator and still pass.

## 5. Potential Risks and Mitigation

*   **Breaking Change**: Callers expecting an array from `make()` will need to adapt. This is a breaking change.
    *   **Mitigation**: Clearly document the change in release notes. Provide examples of how to consume the generator (e.g., `foreach`, `iterator_to_array()`). This change is usually well-justified by the memory benefits for a major version update.
*   **Consuming the Generator Multiple Times**: A generator can typically be iterated only once. If a user tries to iterate over the returned generator multiple times, the second and subsequent iterations will yield nothing.
    *   **Mitigation**: Document this standard behavior of generators. If multiple iterations are a common use case and the previous array behavior was relied upon for this, users might need to call `iterator_to_array()` themselves if they need a reusable collection. The primary use case for `FactoryBatch` usually implies a single processing pass.
*   **Exception Handling**: Exceptions occurring during the generation of an item inside the loop will propagate out of the generator when that item is accessed.
    *   **Mitigation**: This is standard generator behavior. Users consuming the generator should be prepared to handle exceptions during iteration if `FactoryManager::make()` can throw.

This architectural change significantly improves the memory profile of the batch creation feature, making it suitable for much larger datasets. 