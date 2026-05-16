<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Uppercase Rule - Cost: 5
 * String must be uppercase
 */
class Uppercase extends AbstractCaseTransformRule
{
    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be uppercase.";
    }

    protected function transform(string $value): string
    {
        return mb_strtoupper($value);
    }
}
