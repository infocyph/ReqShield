<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * GreaterThan Rule - Cost: 3
 */
class GreaterThan extends NumericFieldComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be greater than {$this->otherField}.";
    }

    protected function compareValues(mixed $value, mixed $other): bool
    {
        return $value > $other;
    }
}
