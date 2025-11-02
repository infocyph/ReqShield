<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AlphaDash Rule - Cost: 15
 * Validates that a value contains only alphanumeric characters, dashes, and
 * underscores.
 */
class AlphaDash extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} may only contain letters, numbers, dashes, and underscores.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_string($value) && preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $value,
        ) === 1;
    }

}
