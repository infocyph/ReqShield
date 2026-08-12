<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Rules\EnumRule;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

final class Rule
{
    public static function enum(string $enumClass): EnumRule
    {
        return new EnumRule($enumClass);
    }

    public static function exists(string $table, string $column): Exists
    {
        return new Exists($table, $column);
    }

    public static function unique(string $table, string $column): Unique
    {
        return new Unique($table, $column);
    }
}
