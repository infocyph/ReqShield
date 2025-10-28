<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Email Rule - Cost: 10
 * Validates that a value is a valid email address.
 */
class Email extends BaseRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid email address.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
