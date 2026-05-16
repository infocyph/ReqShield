<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MaxDigits Rule - Cost: 5
 */
class MaxDigits extends AbstractDigitCountRule
{
    public function message(string $field): string
    {
        return "The {$field} must not exceed {$this->digits} digits.";
    }

    protected function passesForDigitCount(int $count): bool
    {
        return $count <= $this->digits;
    }
}
