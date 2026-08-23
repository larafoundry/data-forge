<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes\Objects;

use Axiom\DataForge\Attributes\ArrayOf;
use Axiom\DataForge\Attributes\MapKey;
use Axiom\DataForge\Concerns\AsStrictInputDto;
use Illuminate\Support\Collection;

final class StrictDtoWithMapKeyNestedCollection
{
    use AsStrictInputDto;

    /**
     * @param  Collection<int, MapKeyCollectionMemberDto>  $people
     */
    public function __construct(
        #[MapKey('members')]
        #[ArrayOf(MapKeyCollectionMemberDto::class)]
        public readonly Collection $people,
    ) {}
}
