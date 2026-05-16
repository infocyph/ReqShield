<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * GreaterThanOrEqual Rule - Cost: 3
 */
class GreaterThanOrEqual extends NumericFieldComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be greater than or equal to {$this->otherField}.";
    }

    protected function compareValues(mixed $value, mixed $other): bool
    {
        return $value >= $other;
    }
}
