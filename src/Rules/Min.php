<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Min Rule - Cost: 2
 * Validates minimum value/length.
 */
class Min extends AbstractSizeRule
{
    public function message(string $field): string
    {
        return "The {$field} must be at least {$this->target}.";
    }

    protected function passesForSize(int|float|string $size): bool
    {
        return $size >= $this->target;
    }
}
