<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Required Rule - Cost: 1
 * Validates that a value is not empty.
 */
class Required extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if ((is_array($value) || is_countable($value)) && count($value) === 0) {
            return false;
        }

        return true;
    }
}
