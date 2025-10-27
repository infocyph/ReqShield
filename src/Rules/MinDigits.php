<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MinDigits Rule - Cost: 5
 */
class MinDigits extends BaseRule
{
    protected int $min;
    public function __construct(int $min)
    {
        $this->min = $min;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be at least {$this->min} digits.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_numeric($value) && strlen((string)$value) >= $this->min;
    }
}
