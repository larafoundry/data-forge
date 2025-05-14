<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes\Objects;

use Ws\DataBridge\Attributes\MapKey;

class DtoWithMapKey
{
    public function __construct(
        #[MapKey('first_name')]
        public readonly string $firstName,

        #[MapKey('last_name')]
        public readonly string $lastName,

        #[MapKey('email_address')]
        public readonly string $email,

        public readonly int $age
    ) {}
}
