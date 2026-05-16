<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Numeric Rule - Cost: 1
 * Validates that a value is numeric.
 */
class Numeric extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a number.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return is_numeric($value);
    }
}
