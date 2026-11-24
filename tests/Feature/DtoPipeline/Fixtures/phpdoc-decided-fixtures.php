<?php

declare(strict_types=1);

/*
 * Loaded with require_once by PhpDocDecidedBehaviorTest. It declares the same
 * namespace more than once, which PSR-4 autoloading cannot load.
 *
 * Excluded from Pint (pint.json): Pint would rewrite the grouped import and
 * strip the `@var` variable name that these fixtures exist to pin.
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

    trait HasPeople
    {
        /** @var Person[] */
        public array $people = [];
    }

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

namespace Tests\Feature\DtoPipeline\KnownLimitations\GroupedImport {
    use Axiom\DataForge\Concerns\AsDto;
    use Tests\Feature\{AsDto\Objects\BasicDto as Person};

    final class GroupedImportDto
    {
        use AsDto;

        /**
         * @param  Person[]  $people
         */
        public function __construct(public readonly array $people) {}
    }
}

namespace Tests\Feature\DtoPipeline\KnownLimitations\VarName {
    use Axiom\DataForge\Concerns\AsDto;
    use Tests\Feature\AsDto\Objects\BasicDto as Person;

    final class OtherVariableVarDto
    {
        use AsDto;

        /** @var Person[] $owners */
        public array $reviewers = [];
    }
}
