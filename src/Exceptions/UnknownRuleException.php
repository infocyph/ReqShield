<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class UnknownRuleException extends ReqShieldException
{
    public static function forName(string $rule): self
    {
        return new self("Unknown validation rule: '{$rule}'");
    }
}
