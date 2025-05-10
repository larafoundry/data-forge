<?php

namespace Tests\Unit\AsDto\Objects;

enum EnumType: string
{
    case REQUIRED = 'required';
    case OPTIONAL = 'optional';
    case PARTIAL = 'partial';
}