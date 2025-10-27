<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * IP Rule - Cost: 10
 * Validates that a value is a valid IP address.
 */
class Ip extends BaseRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid IP address.";
    }

    public function passes($value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
