<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline\Objects;

use Tests\Feature\AsDto\Objects\BasicDto as Member;

/**
 * Its PHPDoc uses an import that only exists in this file, so it resolves
 * differently in a class that uses the trait from another namespace.
 */
trait PhpDocMemberPropertyTrait
{
    /** @var Member[] */
    public array $members = [];
}
