<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * LessThanOrEqual Rule - Cost: 3
 */
class LessThanOrEqual extends NumericFieldComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be less than or equal to {$this->otherField}.";
    }

    protected function compareValues(mixed $value, mixed $other): bool
    {
        return $value <= $other;
    }
}
