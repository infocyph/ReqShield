<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Rules\EnumRule;

final class Rule
{
    public static function enum(string $enumClass): EnumRule
    {
        return new EnumRule($enumClass);
    }
}
