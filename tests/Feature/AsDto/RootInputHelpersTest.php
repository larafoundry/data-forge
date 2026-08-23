<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Axiom\DataForge\Exceptions\UnknownInputKeyException;
use Axiom\DataForge\Exceptions\ValidationException;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\TestCase;

final class RootInputHelpersTest extends TestCase
{
    public function test_from_json_creates_dto_from_json_object(): void
    {
        $dto = RootInputUserDto::fromJson('{"first_name":"Ada","age":37}');

        $this->assertSame('Ada', $dto->firstName);
        $this->assertSame(37, $dto->age);
    }

    public function test_from_json_uses_normal_nested_pipeline(): void
    {
        $dto = RootInputAccountDto::fromJson('{"user":{"first_name":"Ada","age":37}}');

        $this->assertSame('Ada', $dto->user->firstName);
        $this->assertSame(37, $dto->user->age);
    }

    public function test_from_json_preserves_strict_unknown_key_behavior(): void
    {
        $this->expectException(UnknownInputKeyException::class);

        RootInputUserDto::fromJson('{"first_name":"Ada","age":37,"extra":true}');
    }

    public function test_from_json_rejects_invalid_json(): void
    {
        try {
            RootInputUserDto::fromJson('{"first_name":');

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['The JSON payload must be valid JSON.'], $e->errors['json']);
        }
    }

    public function test_from_json_rejects_scalar_and_list_roots(): void
    {
        foreach (['"Ada"', '["Ada"]'] as $json) {
            try {
                RootInputUserDto::fromJson($json);

                $this->fail('Validation should have failed');
            } catch (ValidationException $e) {
                $this->assertSame(['The JSON payload must decode to an object.'], $e->errors['json']);
            }
        }
    }

    public function test_from_arrayable_creates_dto_from_string_keyed_array(): void
    {
        $dto = RootInputUserDto::fromArrayable(new RootInputArrayable([
            'first_name' => 'Ada',
            'age' => 37,
        ]));

        $this->assertSame('Ada', $dto->firstName);
        $this->assertSame(37, $dto->age);
    }

    public function test_from_arrayable_rejects_non_array_root(): void
    {
        try {
            RootInputUserDto::fromArrayable(new RootInputArrayable('nope'));

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['The arrayable payload must return an array.'], $e->errors['attributes']);
        }
    }

    public function test_from_arrayable_rejects_non_string_keyed_root(): void
    {
        try {
            RootInputUserDto::fromArrayable(new RootInputArrayable(['Ada']));

            $this->fail('Validation should have failed');
        } catch (ValidationException $e) {
            $this->assertSame(['The arrayable payload must return a string-keyed array.'], $e->errors['attributes']);
        }
    }
}

final class RootInputUserDto
{
    use AsStrictInputDto;

    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,
        public readonly int $age,
    ) {}
}

final class RootInputAccountDto
{
    use AsDto;

    public function __construct(public readonly RootInputUserDto $user) {}
}

/**
 * @implements Arrayable<array-key,mixed>
 */
final class RootInputArrayable implements Arrayable
{
    public function __construct(private readonly mixed $payload) {}

    public function toArray()
    {
        return $this->payload;
    }
}
