<?php

declare(strict_types=1);

namespace Ws\DataBridge\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }
}
