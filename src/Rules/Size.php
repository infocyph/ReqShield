<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Size Rule - Cost: 2
 * Validates exact size (string length, array count, file size in KB)
 */
class Size extends AbstractSizeRule
{
    public function message(string $field): string
    {
        return "The {$field} must be exactly {$this->target}.";
    }

    protected function passesForSize(int|float|string $size): bool
    {
        return $size === $this->target;
    }
}
