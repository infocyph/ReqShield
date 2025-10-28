<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Distinct Rule - Cost: 10
 * Array values must be unique (no duplicates)
 */
class Distinct extends BaseRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} field has duplicate values.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_array($value) && count($value) === count(array_unique($value, SORT_REGULAR));
    }
}
