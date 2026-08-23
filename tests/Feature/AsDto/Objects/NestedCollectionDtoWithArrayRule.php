<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Concerns\AsDto;
use Illuminate\Support\Collection;

class NestedCollectionDtoWithArrayRule
{
    use AsDto;

    /**
     * @param  Collection<int, BasicDto>  $people
     */
    public function __construct(
        public readonly string $title,
        #[ArrayOf(BasicDto::class)]
        public readonly Collection $people
    ) {}

    /**
     * @return array<string, string|array>
     */
    public static function rules(): array
    {
        return [
            'people' => 'required|array',
        ];
    }
}
