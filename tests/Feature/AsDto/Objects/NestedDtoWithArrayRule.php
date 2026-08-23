<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

use Axiom\DataForge\Concerns\AsDto;

class NestedDtoWithArrayRule
{
    use AsDto;

    public function __construct(
        public readonly string $title,
        public readonly BasicDto $person
    ) {}

    /**
     * @return array<string, string|array>
     */
    public static function rules(): array
    {
        return [
            'person' => 'required|array',
        ];
    }
}
