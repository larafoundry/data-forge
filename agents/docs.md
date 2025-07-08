Cảm ơn bạn. Mình sẽ tạo bản tổng quan ở định dạng Markdown về project PHP của bạn, tập trung trình bày logic nội bộ để các AI/Agent hiểu rõ cách hoạt động của package.
Mình sẽ bắt đầu phân tích repository và sẽ quay lại sớm với bản tóm tắt đầy đủ.


# DataForge Internal Logic Overview

## Architectural Overview

**DataForge** is a PHP library (namespace **`Axiom\DataForge`**) designed to construct and validate Data Transfer Objects (DTOs) from raw input data, with support for automatic **validation** and **random test data generation**. Internally, it leverages PHP **reflection** and Laravel’s **validation** framework to enforce type correctness and required fields. The codebase is structured into distinct components (traits, attributes, core classes, etc.) that work together to inspect DTO classes, apply validation rules, generate random data for missing fields, and instantiate DTO objects with the validated data.

## DTO Trait and Class Setup (AsDto)

The library provides a trait **`AsDto`** (in `Axiom\DataForge\Concerns`) for DTO classes. This trait adds convenient static methods to transform an associative array into a strongly-typed object. Specifically, `AsDto` defines:

* **`fromArray(array $attributes): static`** – A static factory that creates a new DTO instance from a data array. It uses the DataForge core internally: first constructing a `DtoInspector` for the class, then a `Validator` with any user-defined rules (by calling `static::rules()` and `static::messages()` if provided in the DTO class), and finally returns a new instance via the container helper. This ensures the input data is validated and properly mapped to the DTO’s properties before instantiation.
* **`factory(): FactoryManager`** – A static method returning a **`FactoryManager`** for the class. This allows building objects using a fluent API (setting properties and optionally auto-filling missing data) as described below.
* **`rules(): array`** and **`messages(): array`** – Static placeholder methods meant to be overridden in the DTO class to provide custom validation rules or error messages (following Laravel’s validation syntax). By default these return empty arrays, but if defined, they will be merged into the validation process when using `fromArray`.

Using the `AsDto` trait, a DTO class can be defined with public or constructor properties, and then easily constructed from data or via factories without writing boilerplate. The trait is the entry point that connects user-defined DTOs with DataForge’s internal logic.

## Property Metadata Attributes

DataForge defines a set of PHP **attributes** (in `Axiom\DataForge\Attributes`) that can be used to annotate DTO class properties with additional metadata or constraints:

* **`#[MapKey("external_name")]`** – Maps a DTO property to a differently named key in input data. When present, DataForge will treat the given external key as the source for that property’s value. For example, `#[MapKey("id")] public int $userId;` means the DTO’s `$userId` property corresponds to an `id` field in input. The inspector checks for this attribute and swaps the key name accordingly.
* **`#[Min(value)]`** and **`#[Max(value)]`** – Define minimum or maximum numeric values allowed for a numeric property (int or float). For instance, `#[Min(0)] public int $age` indicates \$age should not be less than 0. The attribute classes simply store the given limit. (Currently, these constraints are not automatically enforced by the core validation logic unless the user adds equivalent rules via `rules()`; they primarily serve as documentation or for potential future integration.)
* **`#[StringLength(min, max)]`** – Specifies allowed string length range for a property. For example, `#[StringLength(3,50)] public string $name` means \$name should be between 3 and 50 characters. The attribute ensures min ≤ max on initialization. Similar to Min/Max, this is metadata that could inform validation rules or random generation.
* **`#[DateFormat(format, timezone)]`** – Indicates a date/time string format for a property (e.g. `#[DateFormat('Y-m-d')] public string $birthday`). This can guide how string inputs are parsed into date objects or how output is formatted. (It is a hint for consistency; actual parsing logic for dates is handled by casting in the Validator, described later.)

These attributes augment the DTO’s schema information. The **DtoInspector** uses them (especially `MapKey`) when mapping input keys to properties, and they could be utilized for enforcing ranges or formats. Together with type hints and default values, attributes give the system a richer understanding of each property’s intended data.

## Reflection-Based Introspection (DtoInspector)

Central to DataForge’s logic is the **`DtoInspector`** class (`Axiom\DataForge\Core\DtoInspector`), which uses PHP reflection to analyze a DTO class structure. When a DTO needs to be built (from array or factory), a `DtoInspector` is created for that class to gather essential information:

* **Required vs Optional Fields:** The inspector identifies which fields (properties or constructor parameters) are *required* versus *optional*. It inspects the class constructor parameters and public properties. Any constructor parameter without a default value (and not explicitly typed as nullable) is considered required; those with a default or that allow null are optional. Similarly, public properties without default values are required, and those with defaults (or implicitly nullable) are optional. This produces a list of required keys that must be present for object creation, and a list of all accepted keys (both required and optional).
* **Type Information:** For a given property name or constructor parameter, the inspector can retrieve its expected **ReflectionType** (if declared). This is used to inform validation and random data generation. For example, the inspector can report that `"age"` expects an `int` or `"createdAt"` expects a `DateTimeInterface` (depending on the DTO’s type hints).
* **Attribute Mapping:** The inspector handles `MapKey` attributes. It provides a mapping of property names to input keys by checking each property for a `MapKey` attribute. Its `getKeyMap()` returns an associative array of `$propertyName => $expectedInputKey` for all properties, defaulting to the same name if no attribute is present. This mapping is used to translate between external data keys and internal property names consistently.
* **Type Acceptance Check:** DtoInspector offers a method `isTypeAcceptedForKey(string $key, mixed $value)` that checks if a given value is of an acceptable type for the specified property/param. It uses reflection to compare the value’s type against the declared type (including support for union or intersection types). For example, if a property is type-hinted `int`, passing a string will return false. If a property allows `int|float`, both types would be accepted. This logic is later used in validation to enforce type correctness.

By encapsulating reflection operations, `DtoInspector` acts as the knowledge base about the DTO class structure. Other components query it to enforce that all required fields are present, to map input keys, and to perform type validation.

## Validation Mechanism (DtoRuleBuilder & Validator)

Once the inspector provides the schema of the DTO, DataForge validates input data against it using an internal **Validator** (which wraps Laravel’s validation engine). Two classes orchestrate this:

* **`DtoRuleBuilder`** (`Axiom\DataForge\Core\DtoRuleBuilder`): This static utility builds an array of validation rules for the DTO, combining default rules inferred from the DTO structure with any custom rules. When validation is initiated, `DtoRuleBuilder::build($inspector, $customRules, $attributes)` is called to get the full rule set. Its logic:

    * Mark all required keys with the Laravel **`required`** rule (ensuring those fields must be present).
    * For each accepted (known) field, if it’s not required and the property’s type allows null, it adds a **`nullable`** rule (meaning absence or null is acceptable).
    * It then adds a custom **type-checking closure** for each field: a closure that will fail validation if the given value is not of a type compatible with the property’s type declaration. This is a dynamic rule that uses `DtoInspector::isTypeAcceptedForKey` internally to ensure, for example, that a field expecting an `int` actually receives an integer, or that an object property receives the correct class instance. If the type check fails, it adds a validation error like “The field has an invalid type.”.
    * Next, any user-specified rules (from the DTO’s static `rules()` or other sources) are merged in. The builder ensures that you cannot add rules for unknown fields – it throws an exception if a rule references a field not found in the DTO. Custom rules for a field are appended to that field’s rule list.
    * Finally, if the input data contains any keys that were not explicitly accounted for by the earlier steps (e.g. optional fields that were provided in the data but not present in the rules array yet), the builder adds a `nullable` rule for those as well. This ensures every incoming field has at least some rule, preventing Laravel’s validator from ignoring fields that have no rules.

* **`Validator`** (`Axiom\DataForge\Core\Validator`): This class wraps Laravel’s **Validation Factory** to actually execute the rules. A `Validator` is constructed with a `DtoInspector` and the input attributes array. It uses Laravel’s `ValidationFactory` under the hood (with a simple `Translator` and Container setup) to perform validation. The typical flow:

    * **Initialization & Casting:** One important step is auto-casting of certain inputs. `Validator::from($inspector, $attributes)` will attempt to **cast string inputs to the correct types** before validation. For example, if a constructor expects a `DateTimeImmutable` and the input is a date string, the validator will convert it to a `DateTimeImmutable` instance. Likewise, if a property is an enum type and a string is provided, it will call the enum’s `from()` method to get the enum value. This `autoCastAttributes` step uses the inspector’s Reflection info to ensure the data is in the right format (enums, Carbon dates, etc.) before applying rules.
    * **Building Rules:** The validator calls `DtoRuleBuilder::build` to generate the full set of rules, passing in any custom rules that may have been added via `withRules()` on the validator or via the DTO’s static rules. It also obtains the inspector’s key map to handle any `MapKey` translations.
    * **Key Mapping:** The input data keys and rule keys might differ if `MapKey` attributes were used. The validator uses the map from inspector to **translate property names to external keys and vice versa**. Before running Laravel’s validation, it remaps the rules and messages to use the external input field names, so that Laravel’s validator looks at the correct keys. After validation, it will map any validation errors back to the internal property names for consistency. Similarly, validated data keys are mapped back to property names. This mapping ensures that attributes like `MapKey` are seamlessly handled (the user can provide data under the external key, but the final object will get it under the proper property name).
    * **Execution & Results:** The validator then runs Laravel’s validation (`$factory->make(...)`). If validation fails, it collects the error messages, converts them into an array keyed by the internal property names, and throws a **`ValidationException`** containing these errors. If validation passes, it retrieves the **validated data** array. It also checks for any cases where multiple input fields map to the same property (which could indicate an ambiguous input), and throws an exception if such duplicates are found (to prevent data loss or confusion). Finally, the validated data (with keys as property names) is returned as an array.

In summary, the **Validator** ensures that the input conforms to the DTO’s requirements: all required fields present, values of correct type, and any additional custom constraints satisfied. It builds on Laravel’s robust validation system but adds a layer of reflection-driven rules to cover types and DTO schema. This mechanism catches errors early — if something is missing or of the wrong type, object creation will fail with a ValidationException rather than constructing an invalid object.

## Factory Pattern for Object Creation (FactoryManager & FactoryBatch)

To facilitate programmatic creation of DTOs (especially with test or dummy data), DataForge provides a **factory** mechanism similar to model factories in frameworks like Laravel. The core of this is the **`FactoryManager<T>`** class (`Axiom\DataForge\Core\FactoryManager`), which implements a fluent builder for a given DTO class, and its companion **`FactoryBatch<T>`** for creating multiple instances.

**FactoryManager** is a generic factory for “one object at a time.” Key aspects of FactoryManager include:

* **Selecting Target Class:** It is initialized for a specific DTO class via `FactoryManager::from(string $class)`. The class type is stored (with a generic template `T` for static analysis). This static constructor is the entry point, and it returns a new FactoryManager instance bound to that DTO type.

* **Setting Field Values:** The factory allows specifying field overrides using `with(string $property, mixed $value)` for single fields and `withValues(array $values)` for bulk assignment. Each call records the given values in an internal array. The `with()` method uses reflection to **validate that the given property name actually exists** on the DTO (either as a declared property or a constructor parameter). If not, it throws an `InvalidArgumentException` – this prevents typos or setting nonexistent fields. These overrides will take precedence over any auto-generated data.

* **Random Fill Toggle:** Calling `fillRandom()` on the factory sets a flag indicating that any fields *not* provided via `with()` should be auto-filled with random data. (By default, if you don’t call `fillRandom()`, any missing fields would simply be left missing, and the creation would fail validation if they were required. With `fillRandom`, required fields that you didn’t set will be populated automatically.)

* **Creating the Object:** Finally, `make(): T` triggers the actual object creation. Internally, `FactoryManager::make()` performs the following sequence:

    1. It creates a new `DtoInspector` for the target class to understand required fields and types.
    2. It starts with the array of values provided via `with()` (if any) as the base data.
    3. If the `shouldFillRandom` flag is true, it calls `fillMissingProperties(...)` to auto-populate any required properties that are not yet in the data. This method uses the inspector to get all required keys and iterates over them. For each required field not already set, it obtains that field’s type and then uses the **RandomDataGenerator** to produce a suitable random value for that type. The result is that after this step, all required fields have some value (either user-provided or random), satisfying the minimum needed to construct the DTO.
    4. Next, it creates a **Validator** for the class (`Validator::from($inspector, $data)`), feeding it the data array (now including random fills if applicable). It then calls `$validator->validate()`, which performs the full validation process described earlier (type checks, required rules, custom rules if any). This yields a **\$validatedData** array or throws an exception if validation failed. (In typical usage, random data generation is designed to produce valid values matching the types, so validation failures here would be rare unless custom rules are very specific.)
    5. Upon successful validation, the factory calls `ContainerHelper::makeInstance($class, $validatedData)` to actually construct the DTO object. This step uses reflection to instantiate the class with the given data (detailed in the next section).
    6. The fully-built DTO instance is returned to the caller.

* **`FactoryManager::random()` Shortcut:** There is also a convenience method `random()` which simply calls `fillRandom()` on an empty factory and immediately creates the object. It generates a completely random instance of the DTO (all required fields random, optional fields left default).

**FactoryBatch** provides a similar API for creating many objects at once. It wraps around FactoryManager:

* Use `FactoryBatch::for(string $class)` to start a batch for a given class.
* Set how many instances to create with `count(int $times)`.
* Optionally call `fillRandom()` to apply random fill to each instance, and `with()`/`withValues()` to set common field values that should be applied to all instances in the batch.
* Calling `make()` on the batch will loop the specified number of times and for each iteration, it builds a FactoryManager for the class, applies the preset values and random-fill flag, and calls `make()` on it. This yields an array of `T` objects. Essentially, FactoryBatch is a thin loop that automates calling FactoryManager multiple times.

Together, FactoryManager and FactoryBatch implement a flexible factory pattern:

* **FactoryManager** focuses on building a single object, allowing mix of explicit values and random data for missing fields.
* **FactoryBatch** repeatedly uses FactoryManager to produce multiple objects in bulk.

These factories rely heavily on the `DtoInspector`, `Validator`, and `RandomDataGenerator` to do their work. Importantly, because FactoryManager uses the same validation step as `fromArray`, it ensures that even randomly filled objects respect the DTO’s type constraints (and any custom rules if those were integrated via the trait’s static rules).

## Automatic Random Data Generation (RandomDataGenerator)

When `fillRandom()` is used, DataForge employs the **`RandomDataGenerator`** (`Axiom\DataForge\Core\RandomDataGenerator`) to supply random values that match the expected types. This component uses the **Faker** library (`fakerphp/faker`) under the hood to generate realistic dummy data. Key points about RandomDataGenerator:

* **Type-based Generation:** The generator’s main method `generate(ReflectionType $type, ?string $propertyName)` produces a random value appropriate for the given type hint. It supports all basic data types and some common classes:

    * For primitive types, it chooses reasonable random values: e.g. `int` yields a random integer (by default between 1 and 1000), `float` yields a random floating-point number, `bool` yields a boolean (random true/false), and `string` yields a random word. If the type is `array`, it creates an array of random words (with a random length).
    * It recognizes PHP date/time types: for any type that implements `DateTimeInterface` (including `DateTime` or `DateTimeImmutable`), it will produce a new `DateTimeImmutable` instance (representing “now” by default).
    * **Special property name heuristics:** If the type is string but the property name suggests certain common data (like “email”, “name”, “address”, etc.), the generator uses Faker’s specific methods to produce a realistic value. For example, a property named "email" will get a fake email address, "firstName"/"lastName" get person names, "phone" gets a phone number, etc.. This heuristic makes the random output more meaningful (e.g., populating an email field with something that looks like an email).
    * **Enumerations:** If the property expects an `enum` type, the generator will randomly pick one of the defined enum cases. It uses reflection to get all cases and returns a random case’s value. This works for both backed enums and pure unit enums.
    * **Complex object types:** If the type is a class (and not one of the known special cases), the generator will try to recursively generate an instance of that class. It does this by calling `FactoryManager::from($className)->fillRandom()->make()` internally. In other words, it leverages the same factory mechanism to build nested DTOs or objects. For example, if a DTO has a property that is another DTO class, RandomDataGenerator will instantiate that sub-object by filling it with random data as well. This recursion has safeguards: it tracks classes currently in generation to avoid infinite loops (circular references), and it limits the depth of nested object creation (default max depth = 3). If these limits are hit (e.g., a recursive structure), it falls back to creating a **placeholder**.
    * **Placeholders for unresolvable cases:** In scenarios where an object cannot be fully generated (due to deep recursion or a class with required constructor params that can’t be auto-filled), the generator attempts to create a placeholder object. It will either instantiate the object with a no-arg constructor if possible, or create an anonymous class as a dummy stand-in. This ensures the generation process doesn’t crash, but obviously the placeholder may not be fully usable beyond satisfying type hints. (This is noted as a fallback for type safety; a future improvement might integrate a proper mocking library for such cases.)

* **Randomness and Configuration:** The generator currently uses fixed ranges and distributions (as noted above, e.g., integers 1–1000, 20% chance to return `null` for nullable types). These are hard-coded “magic numbers” in the current implementation. There are recommendations in the project docs to make these configurable in the future for more control. For now, they provide reasonable defaults for general use.

In summary, **RandomDataGenerator** ensures that whenever DataForge needs to auto-fill data, it creates values that align with the expected data types and common formats. This component is only used internally (there’s no public API for it aside from triggering it via `fillRandom()` on factories), and it significantly simplifies test data creation by handling a wide variety of types automatically.

## Instantiating Objects via Reflection (ContainerHelper)

After validation, the final step is turning the validated data array into an actual DTO object. DataForge uses a utility called **`ContainerHelper`** (`Axiom\DataForge\Core\ContainerHelper`) for this. Despite its name, this class mainly uses **reflection**, not a dependency injection container, to create object instances and assign properties:

* **Class Instantiation:** `ContainerHelper::makeInstance(string $class, array $data)` is called with the target class and the validated data (keys are property names at this stage). It first checks that the class exists, then creates a `ReflectionClass` for it. Using `DtoInspector` again, it retrieves the mapping of property names to input keys (key map) and flips it to get a map of input keys to property names. This is used to convert the given `$data` array’s keys to match constructor parameter names or property names:

    * It iterates over each key-value in the input data. If a key corresponds to an *external* name from a MapKey (i.e. it’s found in the reverse map), it replaces the key with the actual property name. Otherwise it keeps the key as is. The result is a new `$mappedData` array where all keys are the DTO’s own property names.
* **Using Constructor vs. Properties:** With `$mappedData` ready, the helper proceeds to create an instance:

    * It checks if the class has a constructor. If yes, it will try to call that constructor with the appropriate arguments. All constructor parameters are examined in order:

        * If a parameter name exists in `$mappedData`, that value is taken as the argument (and that key is marked as “used”).
        * If the parameter is not in the data but has a default value defined in the constructor, the default is used.
        * If the parameter is required (no default) and missing in the data, it throws an `InvalidArgumentException` because something went wrong – this should not happen after proper validation, as Validator would have caught a missing required field earlier.
    * After gathering all constructor arguments, it invokes `ReflectionClass->newInstanceArgs($args)` to instantiate the object. This effectively calls the DTO’s constructor with the provided values.
    * If the class **has no constructor** (or an empty one), then instantiation is simple: `newInstance()` is called without arguments.
* **Populating Public Properties:** After constructing the object (either via constructor or empty instantiation), there may still be some values in `$mappedData` that were not used as constructor arguments. These would correspond to public properties set post-construction (for example, if the DTO class defines public properties instead of constructor params, or if there were additional optional fields). The ContainerHelper iterates over each key/value in `$mappedData` and, if that property name was *not* consumed in the constructor call and a public property with that name exists on the object, it sets the property on the object to that value. This ensures that all data fields are applied: values either went into the constructor or are directly assigned to public fields.
* The fully populated object is then returned.

By combining these steps, `makeInstance` can handle DTO classes that use constructor injection, public properties, or a mix of both. It respects property name mappings (`MapKey`) and fills in all provided data appropriately. Essentially, this method generalizes object creation so that the DTO classes themselves don’t need any special factory logic – DataForge will supply the constructor parameters and set properties as needed.

*Example:* Suppose a DTO class has a constructor `__construct(public string $name, public int $age, public ?string $email = null)` and we provide data `["name" => "Alice", "age" => 30]`. ContainerHelper will call `new ClassName("Alice", 30)` to instantiate (email will take the default null). If the class instead had public properties with no constructor, say `public string $name; public int $age;`, the helper would do `new ClassName()` then set `$obj->name = "Alice"` and `$obj->age = 30` directly. In both cases, the outcome is the same: a DTO object with the given data.

## Exception Handling (ValidationException)

During the process of building DTOs, the primary failure mode is validation error. For example, if required data is missing, or a field has the wrong type or fails a custom rule, the DataForge Validator will throw a **`ValidationException`** (`Axiom\DataForge\Exceptions\ValidationException`). This exception is a custom class that carries the validation error details:

* The exception stores an array of error messages per field (property name) in its public `$errors` property. For instance, `$errors` might be `["age" => ["The age field is required."]]` if age was missing.
* When constructed, it also composes a human-readable exception message by concatenating all error messages. The default message format is `"The given data was invalid. <error1> <error2> ..."`. This message is primarily for debugging; an AI or agent could parse the structured `$errors` property for more precise handling.
* This exception is thrown by `Validator::validate()` whenever `$validator->fails()` (meaning any validation rule failed). It signals that the input data could not produce a valid DTO. The presence of this exception means no object was created; the calling code would need to catch it if it wants to handle validation failures programmatically.

Other exceptions used in DataForge are mostly standard PHP exceptions (`InvalidArgumentException`, `ReflectionException`, `RuntimeException`) thrown for specific error conditions (like an unknown property name in `with()`, failing to reflect a class, or issues in random data generation). However, `ValidationException` is the most significant for flow control, as it bubbles up whenever the data does not pass the defined rules. Agents using DataForge should be prepared to catch `Axiom\DataForge\Exceptions\ValidationException` and inspect its `$errors` for details on what went wrong.

---

**Summary:** The DataForge package’s internal logic revolves around reflecting on DTO definitions and enforcing their constraints. The **AsDto trait** provides DTO classes with factory methods that funnel into the core process: the **DtoInspector** gleans the class schema, the **Validator** (with help of **DtoRuleBuilder**) checks the input against that schema (types, required fields, etc.), and the **ContainerHelper** instantiates the DTO with validated data. The **FactoryManager/FactoryBatch** add a layer to conveniently generate data, using the **RandomDataGenerator** for missing values. All components interact to ensure that any DTO object produced either is fully valid or an informative exception is thrown. This design allows AI agents or any calling code to reliably construct complex data objects with minimal manual coding, relying on the internal logic to handle validation and data generation rigorously. The structure is modular, with each class focusing on a part of the workflow (introspection, rule building, data creation, assembly), making the package's architecture clear and geared towards extension and maintenance.
