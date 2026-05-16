<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * After Rule - Cost: 25
 * Validates that a date is after another date.
 */
class After extends DateComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a date after {$this->date}.";
    }

    protected function compareDates(
        \DateTimeImmutable $valueDate,
        \DateTimeImmutable $referenceDate,
    ): bool {
        return $valueDate > $referenceDate;
    }
}
