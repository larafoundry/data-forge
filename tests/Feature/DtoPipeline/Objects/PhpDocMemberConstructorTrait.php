<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline\Objects;

use Tests\Feature\AsDto\Objects\BasicDto as Member;

/**
 * Its PHPDoc uses an import that only exists in this file.
 */
trait PhpDocMemberConstructorTrait
{
    /**
     * @param  Member[]  $members
     */
    public function __construct(public readonly array $members) {}
}
