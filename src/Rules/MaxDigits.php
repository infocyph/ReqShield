<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MaxDigits Rule - Cost: 5
 */
class MaxDigits extends BaseRule
{
    protected int $max;
    public function __construct(int $max)
    {
        $this->max = $max;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must not exceed {$this->max} digits.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_numeric($value) && strlen((string)$value) <= $this->max;
    }
}
