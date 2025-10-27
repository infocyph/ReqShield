<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Boolean Rule - Cost: 1
 * Validates that a value is a boolean.
 */
class Boolean extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be true or false.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $acceptable = [true, false, 0, 1, '0', '1'];
        return in_array($value, $acceptable, true);
    }
}
