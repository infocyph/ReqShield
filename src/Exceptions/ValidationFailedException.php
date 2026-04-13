<?php

namespace Infocyph\ReqShield\Exceptions;

class ValidationFailedException extends ValidationException
{
    public function __construct(
        protected array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }

    #[\Override]
    public function getErrors(): array
    {
        return $this->errors;
    }

}
