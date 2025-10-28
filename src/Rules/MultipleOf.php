<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MultipleOf Rule - Cost: 5
 */
class MultipleOf extends BaseRule
{
    protected int|float $divisor;

    public function __construct(int|float $divisor)
    {
        $this->divisor = $divisor;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a multiple of {$this->divisor}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return fmod((float) $value, (float) $this->divisor) === 0.0;
    }
}
