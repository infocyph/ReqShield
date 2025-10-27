<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Integer Rule - Cost: 1
 * Validates that a value is an integer.
 */
class IntegerRule extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be an integer.";
    }

    public function passes($value, string $field, array $data): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
}
