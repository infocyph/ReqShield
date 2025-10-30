<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Timezone Rule - Cost: 20
 */
class Timezone extends BaseRule
{
    public function cost(): int
    {
        return 20;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid timezone.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return in_array($value, \DateTimeZone::listIdentifiers(), true);
    }
}
