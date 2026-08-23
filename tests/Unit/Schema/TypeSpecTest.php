<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Axiom\DataForge\Schema\TypeSpec;
use Countable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stringable;

final class TypeSpecTest extends TestCase
{
    public function test_accepts_values_for_named_nullable_and_union_types(): void
    {
        $this->assertTrue($this->typeSpecFor('mixedValue')->accepts('anything'));

        $string = $this->typeSpecFor('stringValue');
        $this->assertTrue($string->accepts('Ada'));
        $this->assertFalse($string->accepts(123));

        $nullableInt = $this->typeSpecFor('nullableInt');
        $this->assertTrue($nullableInt->accepts(42));
        $this->assertTrue($nullableInt->accepts(null));
        $this->assertFalse($nullableInt->accepts('42'));

        $float = $this->typeSpecFor('floatValue');
        $this->assertTrue($float->accepts(1.5));
        $this->assertTrue($float->accepts(1));
        $this->assertFalse($float->accepts('1.5'));
        $this->assertFalse($float->accepts(true));

        $int = $this->typeSpecFor('intValue');
        $this->assertTrue($int->accepts(1));
        $this->assertFalse($int->accepts(1.5));

        $intOrString = $this->typeSpecFor('intOrString');
        $this->assertTrue($intOrString->accepts(42));
        $this->assertTrue($intOrString->accepts('42'));
        $this->assertFalse($intOrString->accepts(false));
    }

    public function test_accepts_literal_false_and_true_types(): void
    {
        $falseOrInt = $this->typeSpecFor('falseOrInt');

        $this->assertTrue($falseOrInt->accepts(false));
        $this->assertTrue($falseOrInt->accepts(0));
        $this->assertFalse($falseOrInt->accepts(true));

        if (PHP_VERSION_ID < 80200) {
            $this->markTestSkipped('Standalone true type requires PHP 8.2 or newer.');
        }

        $class = __NAMESPACE__.'\\TypeSpecTrueLiteralFixture';
        if (! class_exists($class)) {
            eval('namespace Tests\Unit\Schema; final class TypeSpecTrueLiteralFixture { public true $value; }');
        }

        $type = new TypeSpec((new ReflectionProperty($class, 'value'))->getType());

        $this->assertTrue($type->accepts(true));
        $this->assertFalse($type->accepts(false));
    }

    public function test_accepts_named_class_and_intersection_types(): void
    {
        $classValue = $this->typeSpecFor('classValue');

        $this->assertTrue($classValue->accepts(new TypeSpecNamedClassFixture));
        $this->assertFalse($classValue->accepts(new TypeSpecStringableCountableFixture));

        $intersection = $this->typeSpecFor('stringableCountable');

        $this->assertTrue($intersection->accepts(new TypeSpecStringableCountableFixture));
        $this->assertFalse($intersection->accepts(new TypeSpecStringableOnlyFixture));
    }

    public function test_reports_nullability_size_rule_type_and_named_class(): void
    {
        $this->assertTrue($this->typeSpecFor('nullableInt')->allowsNull());
        $this->assertFalse($this->typeSpecFor('stringValue')->allowsNull());

        $this->assertSame('string', $this->typeSpecFor('stringValue')->sizeRuleType());
        $this->assertSame('numeric', $this->typeSpecFor('nullableInt')->sizeRuleType());
        $this->assertSame('numeric', $this->typeSpecFor('nullableNumeric')->sizeRuleType());
        $this->assertNull($this->typeSpecFor('intOrString')->sizeRuleType());
        $this->assertNull($this->typeSpecFor('arrayValue')->sizeRuleType());

        $this->assertTrue($this->typeSpecFor('nullableInt')->isNumeric());
        $this->assertFalse($this->typeSpecFor('stringValue')->isNumeric());

        $this->assertSame(TypeSpecNamedClassFixture::class, $this->typeSpecFor('classValue')->namedClass());
        $this->assertNull($this->typeSpecFor('stringValue')->namedClass());
        $this->assertNull($this->typeSpecFor('intOrString')->namedClass());
    }

    public function test_missing_reflection_type_accepts_any_value_without_metadata(): void
    {
        $type = new TypeSpec(null);

        $this->assertTrue($type->accepts(null));
        $this->assertTrue($type->accepts('anything'));
        $this->assertFalse($type->allowsNull());
        $this->assertNull($type->sizeRuleType());
        $this->assertFalse($type->isNumeric());
        $this->assertNull($type->namedClass());
    }

    private function typeSpecFor(string $property): TypeSpec
    {
        return new TypeSpec((new ReflectionProperty(TypeSpecFixture::class, $property))->getType());
    }
}

final class TypeSpecFixture
{
    public mixed $mixedValue;

    public string $stringValue;

    public ?int $nullableInt;

    public int $intValue;

    public float $floatValue;

    public int|float|null $nullableNumeric;

    public int|string $intOrString;

    public false|int $falseOrInt;

    public TypeSpecNamedClassFixture $classValue;

    public Countable&Stringable $stringableCountable;

    public array $arrayValue;
}

final class TypeSpecNamedClassFixture {}

final class TypeSpecStringableCountableFixture implements Countable, Stringable
{
    public function __toString(): string
    {
        return 'fixture';
    }

    public function count(): int
    {
        return 1;
    }
}

final class TypeSpecStringableOnlyFixture implements Stringable
{
    public function __toString(): string
    {
        return 'fixture';
    }
}
