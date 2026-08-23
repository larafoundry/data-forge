<?php

declare(strict_types=1);

namespace Tests\Feature\AsDto\Objects;

enum EnumType: string
{
    case REQUIRED = 'required';
    case OPTIONAL = 'optional';
    case PARTIAL = 'partial';
}
