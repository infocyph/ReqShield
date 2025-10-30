<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * URL Rule - Cost: 10
 * Validates that a value is a valid URL.
 */
class Url extends BaseRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid URL.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
