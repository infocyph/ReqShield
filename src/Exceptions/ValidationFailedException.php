<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

class ValidationFailedException extends ValidationException
{
    /** @param array<string, array<int, string>> $errors */
    public function __construct(
        array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message, $errors);
    }
}
