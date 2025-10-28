<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Lowercase Rule - Cost: 5
 * String must be lowercase
 */
class Lowercase extends BaseRule
{
    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be lowercase.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return $value === mb_strtolower($value);
    }
}
