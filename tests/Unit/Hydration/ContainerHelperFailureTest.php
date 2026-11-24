<?php

declare(strict_types=1);

namespace Tests\Unit\Hydration;

use Axiom\DataForge\Exceptions\InstantiationException;
use Axiom\DataForge\Hydration\ContainerHelper;
use Error;
use PHPUnit\Framework\TestCase;
use TypeError;

final class ContainerHelperFailureTest extends TestCase
{
    public function test_class_without_constructor_that_cannot_be_instantiated_is_wrapped(): void
    {
        try {
            ContainerHelper::makeInstance(AbstractWithoutConstructorFixture::class, []);

            $this->fail('Instantiation should have failed');
        } catch (InstantiationException $e) {
            $this->assertSame('Failed to instantiate '.AbstractWithoutConstructorFixture::class, $e->getMessage());
            $this->assertInstanceOf(Error::class, $e->getPrevious());
        }
    }

    public function test_static_properties_are_never_assigned_from_data(): void
    {
        StaticPropertyFixture::$shared = 'original';

        $instance = ContainerHelper::makeInstance(StaticPropertyFixture::class, [
            'name' => 'Ada',
            'shared' => 'overwritten',
        ]);

        $this->assertSame('Ada', $instance->name);
        $this->assertSame('original', StaticPropertyFixture::$shared);
    }

    public function test_assigning_a_value_of_the_wrong_type_is_wrapped(): void
    {
        try {
            ContainerHelper::makeInstance(TypedPublicPropertyFixture::class, ['count' => 'many']);

            $this->fail('Instantiation should have failed');
        } catch (InstantiationException $e) {
            $this->assertSame("Failed to assign property 'count' on ".TypedPublicPropertyFixture::class, $e->getMessage());
            $this->assertInstanceOf(TypeError::class, $e->getPrevious());
        }
    }

    public function test_uninitialized_readonly_property_is_rejected(): void
    {
        $this->expectException(InstantiationException::class);
        $this->expectExceptionMessage('Cannot initialize readonly property: label');

        ContainerHelper::makeInstance(UninitializedReadonlyPropertyFixture::class, []);
    }

    public function test_uninitialized_non_nullable_property_is_rejected(): void
    {
        $this->expectException(InstantiationException::class);
        $this->expectExceptionMessage("Property 'count' was not initialized on ".TypedPublicPropertyFixture::class);

        ContainerHelper::makeInstance(TypedPublicPropertyFixture::class, []);
    }

    public function test_missing_untyped_constructor_parameter_is_required(): void
    {
        $this->expectException(InstantiationException::class);
        $this->expectExceptionMessage('Missing required parameter: value');

        ContainerHelper::makeInstance(UntypedConstructorParameterFixture::class, []);
    }
}

abstract class AbstractWithoutConstructorFixture {}

final class StaticPropertyFixture
{
    public static string $shared = 'original';

    public string $name = '';
}

final class TypedPublicPropertyFixture
{
    public int $count;
}

final class UninitializedReadonlyPropertyFixture
{
    public readonly string $label;
}

final class UntypedConstructorParameterFixture
{
    public mixed $stored;

    /** @param  mixed  $value */
    public function __construct($value)
    {
        $this->stored = $value;
    }
}
