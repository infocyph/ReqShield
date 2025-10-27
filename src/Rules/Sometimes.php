<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Sometimes Rule - Cost: 1
 * Validates only if the field is present (allows undefined fields)
 */
class Sometimes extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return '';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        // Always passes - used as a marker for conditional validation
        return true;
    }
}
