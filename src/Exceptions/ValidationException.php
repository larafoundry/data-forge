<?php

declare(strict_types=1);

namespace Ws\DataBridge\Exceptions;

use Exception;

class ValidationException extends Exception
{
    /**
     * @param  array<string, array<string>>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        $errorMessages = [];
        foreach ($this->errors as $messages) {
            // We know from type hint that $messages is an array, but PHPStan doesn't know
            // we can simply iterate directly as that's what we need
            foreach ($messages as $message) {
                $errorMessages[] = $message;
            }
        }

        $message = 'The given data was invalid. '.implode(' ', $errorMessages);
        parent::__construct($message);
    }
}
