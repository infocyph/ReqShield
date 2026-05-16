<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AlphaNum Rule - Cost: 15
 * Validates that a value contains only alphanumeric characters.
 */
class AlphaNum extends AbstractStringPatternRule
{
    public function message(string $field): string
    {
        return "The {$field} may only contain letters and numbers.";
    }

    protected function pattern(): string
    {
        return '/^[a-zA-Z0-9]+$/';
    }
}
