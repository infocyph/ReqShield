<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MinDigits Rule - Cost: 5
 */
class MinDigits extends AbstractDigitCountRule
{
    public function message(string $field): string
    {
        return "The {$field} must be at least {$this->digits} digits.";
    }

    protected function passesForDigitCount(int $count): bool
    {
        return $count >= $this->digits;
    }
}
