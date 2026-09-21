<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class FrozenSchemaRegistryException extends ReqShieldException
{
    public static function forOperation(string $operation): self
    {
        return new self("Schema registry is frozen; {$operation} is not allowed.");
    }
}
