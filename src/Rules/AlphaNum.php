<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AlphaNum Rule - Cost: 15
 * Validates that a value contains only alphanumeric characters.
 */
class AlphaNum extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} may only contain letters and numbers.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }
}
