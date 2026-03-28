<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class RuleExpressionParser
{
    /**
     * @return array{0:string,1:array<int,string>}
     */
    public static function parse(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $rawParams = $parts[1] ?? '';

        if ($rawParams === '') {
            return [$name, []];
        }

        if (in_array($name, ['regex', 'not_regex'], true)) {
            return [$name, [$rawParams]];
        }

        return [$name, array_values(array_filter(
            explode(',', $rawParams),
            static fn (string $value): bool => $value !== '',
        ))];
    }
}
