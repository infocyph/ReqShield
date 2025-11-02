<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Date Rule - Cost: 20
 * Validates that a value is a valid date.
 */
class Date extends BaseRule
{
    public function cost(): int
    {
        return 20;
    }

    public function message(string $field): string
    {
        return "The {$field} is not a valid date.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        try {
            new \DateTime($value);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
