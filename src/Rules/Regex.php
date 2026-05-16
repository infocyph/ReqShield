<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Regex Rule - Cost: 20
 * Validates that a value matches a regular expression.
 */
class Regex extends AbstractRegexPatternRule
{
    public function message(string $field): string
    {
        return "The {$field} format is invalid.";
    }

    protected function isPatternResultValid(int|false $result): bool
    {
        return $result === 1;
    }
}
