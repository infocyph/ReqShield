<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Filled Rule - Cost: 1
 */
class Filled extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be filled when present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

}
