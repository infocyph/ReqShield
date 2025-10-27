<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Json Rule - Cost: 15
 * Validates that a value is valid JSON.
 */
class Json extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid JSON string.";
    }

    public function passes($value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
