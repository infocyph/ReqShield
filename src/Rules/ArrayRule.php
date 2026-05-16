<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Array Rule - Cost: 1
 * Validates that a value is an array.
 */
class ArrayRule extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be an array.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return is_array($value);
    }
}
