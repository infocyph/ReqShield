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

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_string($value) && json_validate($value);
    }

}
