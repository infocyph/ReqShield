<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Mac Rule - Cost: 15
 * Value must be a valid MAC address
 */
class Mac extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid MAC address.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_MAC) !== false;
    }

}
