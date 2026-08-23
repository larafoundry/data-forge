<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto;

use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class StrictInputDtoTest extends TestCase
{
    public function test_strict_parent_does_not_make_flexible_child_strict(): void
    {
        $dto = StrictParentWithFlexibleChildDto::fromArray([
            'child' => [
                'name' => 'Ada',
                'extra' => 'ignored by flexible child',
            ],
        ]);

        $this->assertSame('Ada', $dto->child->name);
    }

    public function test_strict_parent_rejects_its_own_unknown_keys(): void
    {
        try {
            StrictParentWithFlexibleChildDto::fromArray([
                'child' => [
                    'name' => 'Ada',
                    'extra' => 'ignored by flexible child',
                ],
                'extra' => 'rejected by strict parent',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('extra', $e->errors);
            $this->assertArrayNotHasKey('child.extra', $e->errors);
        }
    }

    public function test_flexible_parent_does_not_make_strict_child_flexible(): void
    {
        try {
            FlexibleParentWithStrictChildDto::fromArray([
                'child' => [
                    'name' => 'Ada',
                    'extra' => 'rejected by strict child',
                ],
                'extra' => 'ignored by flexible parent',
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('child.extra', $e->errors);
            $this->assertArrayNotHasKey('extra', $e->errors);
        }
    }

    public function test_strict_input_reports_integer_unknown_keys_without_type_error(): void
    {
        try {
            StrictChildDto::fromArray([
                'name' => 'Ada',
                0 => 'rejected numeric key',
            ]);

            $this->fail('Validation should have failed');
        } catch (UnknownInputKeyException $e) {
            $this->assertSame(['The 0 field is not allowed.'], $e->errors['0'] ?? null);
        }
    }

    public function test_strict_input_reports_numeric_string_unknown_keys_without_type_error(): void
    {
        try {
            StrictChildDto::fromArray([
                'name' => 'Ada',
                '7' => 'rejected numeric-string key',
            ]);

            $this->fail('Validation should have failed');
        } catch (UnknownInputKeyException $e) {
            $this->assertSame(['The 7 field is not allowed.'], $e->errors['7'] ?? null);
        }
    }

    public function test_strict_input_is_not_inherited_from_parent_class(): void
    {
        $dto = FlexibleChildOfStrictBaseDto::fromArray([
            'name' => 'Ada',
            'extra' => 'ignored because the child class is not strict',
        ]);

        $this->assertSame('Ada', $dto->name);
        $this->assertFalse(property_exists($dto, 'extra'));
    }
}

final class FlexibleChildDto
{
    use AsDto;

    public function __construct(public readonly string $name) {}
}

final class StrictChildDto
{
    use AsStrictInputDto;

    public function __construct(public readonly string $name) {}
}

final class StrictParentWithFlexibleChildDto
{
    use AsStrictInputDto;

    public function __construct(public readonly FlexibleChildDto $child) {}
}

final class FlexibleParentWithStrictChildDto
{
    use AsDto;

    public function __construct(public readonly StrictChildDto $child) {}
}

class StrictBaseDto
{
    use AsStrictInputDto;

    public function __construct(public readonly string $name) {}
}

final class FlexibleChildOfStrictBaseDto extends StrictBaseDto {}
