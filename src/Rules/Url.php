<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * URL Rule - Cost: 10
 * Validates that a value is a valid URL.
 */
class Url extends AbstractFilterVarRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid URL.";
    }

    protected function filterType(): int
    {
        return FILTER_VALIDATE_URL;
    }
}
