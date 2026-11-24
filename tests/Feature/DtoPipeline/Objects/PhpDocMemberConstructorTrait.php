<?php

declare(strict_types=1);

namespace Tests\Feature\DtoPipeline\Objects;

/**
 * Its PHPDoc uses a fully-qualified class name, so it means the same type in
 * every class that uses the trait.
 */
trait PhpDocMemberConstructorTrait
{
    /**
     * @param  \Tests\Feature\AsDto\Objects\BasicDto[]  $members
     */
    public function __construct(public readonly array $members) {}
}
