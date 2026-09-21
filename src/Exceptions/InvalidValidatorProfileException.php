<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class InvalidValidatorProfileException extends ReqShieldException
{
    public static function forOption(string $option, string $reason): self
    {
        return new self("Invalid validator profile option '{$option}': {$reason}");
    }
}
