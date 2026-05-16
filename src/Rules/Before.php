<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Before Rule - Cost: 25
 * Validates that a date is before another date.
 */
class Before extends DateComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a date before {$this->date}.";
    }

    protected function compareDates(
        \DateTimeImmutable $valueDate,
        \DateTimeImmutable $referenceDate,
    ): bool {
        return $valueDate < $referenceDate;
    }
}
