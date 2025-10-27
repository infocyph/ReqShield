<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Bail Rule - Cost: 1
 */
class Bail extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} stops validation on first failure.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return true; // Always passes, used as marker for stopping validation
    }
}
