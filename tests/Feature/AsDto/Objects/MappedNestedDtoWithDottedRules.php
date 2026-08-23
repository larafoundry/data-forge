<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsDto;

class MappedNestedDtoWithDottedRules
{
    use AsDto;

    public function __construct(
        public readonly string $title,
        #[MapKey('person_data')]
        public readonly BasicDto $person
    ) {}

    /**
     * @return array<string, string|array>
     */
    public static function rules(): array
    {
        return [
            'person.name' => 'required|min:3',
        ];
    }
}
