<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Exclude Rule - Cost: 1
 */
class Exclude extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} will be excluded from validated data.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return false; // This rule always fails and excludes the field
    }
}
