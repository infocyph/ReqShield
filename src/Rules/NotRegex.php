<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * NotRegex Rule - Cost: 20
 */
class NotRegex extends AbstractRegexPatternRule
{
    public function message(string $field): string
    {
        return "The {$field} format is invalid (must not match pattern).";
    }

    protected function isPatternResultValid(int|false $result): bool
    {
        return $result === 0;
    }
}
