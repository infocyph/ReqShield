<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Digits Rule - Cost: 5
 */
class Digits extends AbstractDigitCountRule
{
    public function message(string $field): string
    {
        return "The {$field} must be {$this->digits} digits.";
    }

    protected function passesForDigitCount(int $count): bool
    {
        return $count === $this->digits;
    }
}
