<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Declined Rule - Cost: 1
 * Field must be "no", "off", 0, or false
 */
class Declined extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be declined.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return in_array($value, ['no', 'off', '0', 0, false, 'false'], true);
    }
}
