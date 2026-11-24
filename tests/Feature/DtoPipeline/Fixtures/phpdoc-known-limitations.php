<?php

declare(strict_types=1);

/*
 * Loaded with require_once by PhpDocDecidedBehaviorTest. It declares the same
 * namespace more than once, which PSR-4 autoloading cannot load.
 */

namespace Tests\Feature\DtoPipeline\KnownLimitations {
    use Axiom\DataForge\Concerns\AsDto;
    use Tests\Feature\AsDto\Objects\BasicDto as Member;

    final class FirstBlockDto
    {
        use AsDto;

        /**
         * @param  Member[]  $members
         */
        public function __construct(public readonly array $members) {}
    }
}

namespace Tests\Feature\DtoPipeline\KnownLimitations {
    use Axiom\DataForge\Concerns\AsDto;
    use Tests\Feature\DtoPipeline\DocOtherItemDto as Member;

    final class SecondBlockDto
    {
        use AsDto;

        /**
         * @param  Member[]  $members
         */
        public function __construct(public readonly array $members) {}
    }
}

namespace Tests\Feature\DtoPipeline\KnownLimitations\Traits {
    use Tests\Feature\DtoPipeline\DocOtherItemDto as Person;

    trait InitializesPeople
    {
        public array $people = [];

        /**
         * @param  Person[]  $people
         */
        public function init(array $people): void
        {
            $this->people = $people;
        }
    }
}

namespace Tests\Feature\DtoPipeline\KnownLimitations\Aliased {
    use Axiom\DataForge\Concerns\AsDto;
    use Tests\Feature\DtoPipeline\KnownLimitations\Traits\InitializesPeople;

    final class AliasedConstructorDto
    {
        use AsDto;
        use InitializesPeople {
            init as __construct;
        }
    }
}
