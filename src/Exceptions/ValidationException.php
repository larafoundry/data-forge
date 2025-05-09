<?php

declare(strict_types=1);

namespace Ws\DataBridge\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function __construct(public readonly array $errors)
    {
        $errorMessages = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $errorMessages[] = $message;
            }
        }
        
        $message = 'The given data was invalid. ' . implode(' ', $errorMessages);
        parent::__construct($message);
    }
}
