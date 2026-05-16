<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * LessThan Rule - Cost: 3
 */
class LessThan extends NumericFieldComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be less than {$this->otherField}.";
    }

    protected function compareValues(mixed $value, mixed $other): bool
    {
        return $value < $other;
    }
}
