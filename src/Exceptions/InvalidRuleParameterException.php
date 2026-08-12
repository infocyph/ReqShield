<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class InvalidRuleParameterException extends ReqShieldException
{
    public static function forRule(string $rule, string $reason): self
    {
        return new self("Invalid parameters for rule '{$rule}': {$reason}");
    }
}
