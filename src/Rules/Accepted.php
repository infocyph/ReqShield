<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Accepted Rule - Cost: 1
 * Field must be "yes", "on", 1, or true (useful for terms acceptance)
 */
class Accepted extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be accepted.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return in_array(
            $value,
            ['yes', 'on', '1', 1, true, 'true'],
            true
        );
    }
}
