<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Regression: when a strict nested DTO rejects a raw (unknown) input key, the
 * error key must preserve the exact key the caller sent. Only the *path* to the
 * nested DTO is rendered in the caller's namespace; the rejected leaf key is
 * already an input key and must not be mapped as a canonical property name.
 *
 * Before the fix, ErrorKeyMapper mapped every path segment, so a rejected
 * `firstName` was rewritten to `first_name` — pointing at the valid mapped
 * field while the message complained about `firstName`.
 */
final class NestedStrictErrorKeyTest extends TestCase
{
    public function test_nested_strict_unknown_key_is_not_masked_by_required_validation(): void
    {
        try {
            NestedStrictParentFixture::fromArray([
                'child_data' => [
                    'firstName' => 'Jane',
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('child_data.firstName', $e->errors);
            $this->assertArrayNotHasKey('child_data', $e->errors);
            $this->assertArrayNotHasKey('child_data.first_name', $e->errors);
        }
    }

    public function test_nested_strict_unknown_key_preserves_caller_key_through_mapped_path(): void
    {
        try {
            NestedStrictParentFixture::fromArray([
                'child_data' => [
                    'first_name' => 'John', // valid mapped key, so hydration is reached
                    'firstName' => 'Jane',  // raw key -> strict reject, must surface verbatim
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('child_data.firstName', $e->errors);
            $this->assertArrayNotHasKey('child_data.first_name', $e->errors);
        }
    }

    public function test_nested_strict_unknown_key_in_collection_is_not_masked_by_required_validation(): void
    {
        try {
            NestedStrictCollectionParentFixture::fromArray([
                'members' => [
                    [
                        'firstName' => 'Jane',
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('members.0.firstName', $e->errors);
            $this->assertArrayNotHasKey('members.0.first_name', $e->errors);
        }
    }

    public function test_nested_strict_unknown_key_in_collection_preserves_caller_key(): void
    {
        try {
            NestedStrictCollectionParentFixture::fromArray([
                'members' => [
                    [
                        'first_name' => 'John',
                        'firstName' => 'Jane',
                    ],
                ],
            ]);

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('members.0.firstName', $e->errors);
            $this->assertArrayNotHasKey('members.0.first_name', $e->errors);
        }
    }
}

final class NestedStrictChildFixture
{
    use AsStrictInputDto;

    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,
    ) {}
}

final class NestedStrictParentFixture
{
    use AsDto;

    public function __construct(
        #[MapKey('child_data')]
        public readonly NestedStrictChildFixture $child,
    ) {}
}

final class NestedStrictCollectionParentFixture
{
    use AsDto;

    /**
     * @param  Collection<int, NestedStrictChildFixture>  $children
     */
    public function __construct(
        #[MapKey('members')]
        #[ArrayOf(NestedStrictChildFixture::class)]
        public readonly Collection $children,
    ) {}
}
