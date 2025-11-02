<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * In Rule - Cost: 5
 * Validates that a value is in a list of acceptable values.
 */
class In extends BaseRule
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
        return in_array($value, $this->values, true);
    }

}
