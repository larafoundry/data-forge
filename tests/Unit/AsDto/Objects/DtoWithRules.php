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
        public readonly string $email
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => [
                'required', 
                'string', 
                ['min', 3]
            ],
            'age' => [
                'required', 
                'integer', 
                ['min', 18]
            ],
            'email' => [
                'required', 
                'email'
            ],
        ];
    }
}
