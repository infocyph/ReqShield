<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class FrozenValidatorException extends ReqShieldException
{
    public static function forMutation(string $operation): self
    {
        return new self("Validator topology is frozen; {$operation} is not allowed.");
    }
}
