<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Uuid Rule - Cost: 15
 * Value must be a valid UUID
 */
class Uuid extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid UUID.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
