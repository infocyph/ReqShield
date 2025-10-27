<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Alpha Rule - Cost: 15
 * Validates that a value contains only alphabetic characters.
 */
class Alpha extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} may only contain letters.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }
}
