<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Max Rule - Cost: 2
 * Validates maximum value/length.
 */
class Max extends AbstractSizeRule
{
    public function message(string $field): string
    {
        return "The {$field} must not exceed {$this->target}.";
    }

    protected function passesForSize(int|float|string $size): bool
    {
        return $size <= $this->target;
    }
}
