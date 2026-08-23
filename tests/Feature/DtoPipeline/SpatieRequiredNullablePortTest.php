<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class SpatieRequiredNullablePortTest extends TestCase
{
    public function test_non_nullable_constructor_parameter_is_required(): void
    {
        try {
            SpatieRequiredNameDto::fromArray([]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_nullable_constructor_parameter_is_optional_and_accepts_explicit_null(): void
    {
        $missing = SpatieNullableLabelDto::fromArray([]);
        $explicitNull = SpatieNullableLabelDto::fromArray([
            'label' => null,
        ]);
        $provided = SpatieNullableLabelDto::fromArray([
            'label' => 'provided',
        ]);

        $this->assertNull($missing->label);
        $this->assertNull($explicitNull->label);
        $this->assertSame('provided', $provided->label);
    }

    public function test_explicit_required_rule_makes_nullable_parameter_required(): void
    {
        try {
            SpatieRequiredNullableLabelDto::fromArray([]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('label', $e->errors);
        }

        try {
            SpatieRequiredNullableLabelDto::fromArray([
                'label' => null,
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('label', $e->errors);
        }
    }

    public function test_defaulted_public_property_is_not_validated_when_omitted(): void
    {
        $missing = SpatieDefaultedPublicPropertyDto::fromArray([]);
        $provided = SpatieDefaultedPublicPropertyDto::fromArray([
            'name' => 'Long enough',
        ]);

        $this->assertSame('Hello World', $missing->name);
        $this->assertSame('Long enough', $provided->name);

        try {
            SpatieDefaultedPublicPropertyDto::fromArray([
                'name' => 'short',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_explicit_required_rule_validates_defaulted_property_even_when_omitted(): void
    {
        try {
            SpatieRequiredDefaultedPublicPropertyDto::fromArray([]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }
}

final class SpatieRequiredNameDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class SpatieNullableLabelDto
{
    use AsDto;

    public function __construct(public readonly ?string $label) {}
}

final class SpatieRequiredNullableLabelDto
{
    use AsDto;

    public function __construct(public readonly ?string $label) {}

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'label' => 'required|string',
        ];
    }
}

final class SpatieDefaultedPublicPropertyDto
{
    use AsDto;

    public string $name = 'Hello World';

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'string|min:10',
        ];
    }
}

final class SpatieRequiredDefaultedPublicPropertyDto
{
    use AsDto;

    public string $name = 'Hello World';

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:10'],
        ];
    }
}
