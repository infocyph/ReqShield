<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DigitsBetween Rule - Cost: 5
 */
class DigitsBetween extends BaseRule
{
    protected int $max;

    protected int $min;

    public function __construct(int $min, int $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be between {$this->min} and {$this->max} digits.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_numeric($value)) {
            return false;
        }
        $length = strlen((string) $value);

        return $length >= $this->min && $length <= $this->max;
    }
}
