<?php

declare(strict_types=1);

namespace Tests\Unit\AsDto\Objects;

use Ws\DataBridge\Concerns\AsDto;

class DtoWithRules
{
    use AsDto;

    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly string $email,
        public array $tags,
        public EnumType $enumType
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
            'age' => 'required|integer|min:18',
            'email' => 'required|email',
        ];
    }

    public static function messages(): array
    {
        return [
            'name.min' => 'The name must be at least :min characters.',
            'age.min' => 'You must be at least :min years old.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
