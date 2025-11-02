<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Ulid Rule - Cost: 15
 */
class Ulid extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid ULID.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_string($value) && preg_match(
            '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/',
            $value,
        ) === 1;
    }

}
