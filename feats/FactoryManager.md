### **FactoryManager 2.0 & FactoryBatch — Functional Requirements**

*Author: Nguyen Trung* *Status: Ready for implementation*

---

## 1  – Purpose

Upgrade the existing `Ws\DataBridge\Core\FactoryManager` so developers can

1. **Populate unset properties automatically with random data** derived from PHP types.
2. **Generate many DTOs in one call** through a companion `FactoryBatch`.

---

## 2  – Glossary

| Term            | Meaning                                                                                      |
| --------------- | -------------------------------------------------------------------------------------------- |
| **DTO**         | Any immutable data-transfer or value object.                                                 |
| **Random fill** | Supplying unset properties with pseudo-random values based on the PHP type of each property. |
| **Batch**       | A collection of objects produced in a single factory call.                                   |

---

## 3  – System Constraints

* Runtime **PHP ≥ 8.2**.
* Random data generation relies on **fakerphp/faker**; no extra “pluggable” layer is required in v1.
* Code must be free of warnings on **Psalm level 2** and **PHPStan max**.

---

## 4  – Public API Specification

### 4.1  FactoryManager\<T>

```php
namespace Ws\DataBridge\Core;

/**
 * Generic object factory.
 *
 * @template T of object
 * @psalm-type Override = array<string,mixed>
 */
final class FactoryManager
{
    /** Static entry point */
    public static function from(string $class): self;

    /** Set / override a single property */
    public function with(string $property, mixed $value): self;

    /** Set / override multiple properties at once */
    public function withValues(array $values): self;

    /** Auto-fill every missing property with random data */
    public function fillRandom(): self;

    /** Build the DTO (runs validation) @return T */
    public function make(): object;
}
```

### 4.2  FactoryBatch\<T>

```php
namespace Ws\DataBridge\Core;

/**
 * Factory for multiple objects of the same type.
 *
 * @template T of object
 */
final class FactoryBatch
{
    public static function for(string $class): self;
    public function count(int $times): self;
    public function fillRandom(): self;

    /** @return array<int,T> */
    public function make(): array;
}
```

---

## 5  Random Data Generation

A dedicated class **`RandomDataGenerator`** lives in `Ws\DataBridge\Core` and is **used internally only**.

```php
namespace Ws\DataBridge\Core;

use Faker\Generator;

/**
 * Generates random values for supported PHP property types.
 */
final class RandomDataGenerator
{
    public function __construct(private readonly Generator $faker) {}

    /** @param \ReflectionType $type */
    public function generate(mixed $type): mixed
    {
        // Mapping example:
        // int    → $this->faker->numberBetween(1, 1000)
        // float  → $this->faker->randomFloat()
        // string → $this->faker->word()
        // bool   → $this->faker->boolean()
        // DateTimeInterface → new \DateTimeImmutable()
        // BackedEnum → random case
        // object DTO → FactoryManager::from($class)->fillRandom()->make()
    }
}
```

No public hooks are exposed for swapping the generator in this version.

---

## 6  Validation & Error Handling

* Re-use `DtoInspector` and `Validator` to ensure type correctness.
* Raised exceptions:

    * `ReflectionException` — invalid DTO definition.
    * `Ws\DataBridge\Exceptions\ValidationException` — failed validation after random fill / overrides.
    * `RuntimeException` — unrecoverable generator failure (e.g. Faker mis-configuration).

---

## 7  Testing Requirements

* Framework: **Pest PHP** (auto-discovers specs in `/tests`).
* Coverage must include:

    1. Random generation for every supported PHP type.
    2. Precedence of `with()` / `withValues()` over `fillRandom()`.
    3. Batch creation (`FactoryBatch`) producing the requested count.
    4. **Edge cases:** calling `with()` or `withValues()` with a property **not declared** on the DTO must raise an appropriate exception.

---

## 8  Sample Usage

<details>
<summary>Create one DTO with random fill and manual override</summary>

```php
use Ws\DataBridge\Core\FactoryManager;
use App\DTO\UserData;

/** @var UserData $user */
$user = FactoryManager::from(UserData::class)
        ->fillRandom()
        ->with('email', 'nguyen.trung@example.com')
        ->make();
```

</details>

<details>
<summary>Create 50 DTOs with batch factory</summary>

```php
use Ws\DataBridge\Core\FactoryBatch;
use App\DTO\OrderData;

/** @var OrderData[] $orders */
$orders = FactoryBatch::for(OrderData::class)
          ->count(50)
          ->fillRandom()
          ->make();
```

</details>

---

### End of requirements
