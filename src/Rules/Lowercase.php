<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Lowercase Rule - Cost: 5
 * String must be lowercase
 */
class Lowercase extends AbstractCaseTransformRule
{
    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be lowercase.";
    }

    protected function transform(string $value): string
    {
        return mb_strtolower($value);
    }
}
