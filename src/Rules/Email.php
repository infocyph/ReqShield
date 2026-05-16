<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Email Rule - Cost: 10
 * Validates that a value is a valid email address.
 */
class Email extends AbstractFilterVarRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid email address.";
    }

    protected function filterType(): int
    {
        return FILTER_VALIDATE_EMAIL;
    }
}
