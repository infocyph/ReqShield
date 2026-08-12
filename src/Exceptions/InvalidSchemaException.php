<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class InvalidSchemaException extends ReqShieldException
{
    public static function forField(string $field, string $reason): self
    {
        return new self("Invalid schema for '{$field}': {$reason}");
    }
}
