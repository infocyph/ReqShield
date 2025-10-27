<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * NotIn Rule - Cost: 5
 * Validates that a value is not in a list of values.
 */
class NotIn extends BaseRule
{
    protected array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The selected {$field} is invalid.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return !in_array($value, $this->values, true);
    }
}
