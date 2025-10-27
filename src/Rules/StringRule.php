<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * String Rule - Cost: 1
 * Validates that a value is a string.
 */
class StringRule extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a string.";
    }

    public function passes($value, string $field, array $data): bool
    {
        return is_string($value);
    }
}
