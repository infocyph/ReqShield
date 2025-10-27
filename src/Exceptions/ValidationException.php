<?php

namespace Infocyph\ReqShield\Exceptions;

use Exception;

/**
 * Base exception for validation library.
 */
class ValidationException extends Exception
{
}

/**
 * Exception thrown when an invalid rule is provided.
 */
class InvalidRuleException extends ValidationException
{
}

/**
 * Exception thrown when validation fails.
 */
class ValidationFailedException extends ValidationException
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
