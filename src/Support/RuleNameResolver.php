<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class RuleNameResolver
{
    public static function canonicalRuleNameFromClass(string $class): string
    {
        $shortName = self::shortClassName($class);
        $snake = self::toSnakeCase($shortName);

        return str_ends_with($snake, '_rule')
            ? substr($snake, 0, -5)
            : $snake;
    }

    public static function shortClassName(string $class): string
    {
        $class = ltrim($class, '\\');
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    public static function toSnakeCase(string $value): string
    {
        return strtolower(
            preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value,
        );
    }
}
