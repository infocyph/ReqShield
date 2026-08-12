<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class DatabaseProviderRequiredException extends ReqShieldException
{
    public static function forRule(string $rule): self
    {
        return new self("Database-backed validation rule \"{$rule}\" requires a DatabaseProvider.");
    }
}
