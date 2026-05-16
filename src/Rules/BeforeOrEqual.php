<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * BeforeOrEqual Rule - Cost: 25
 */
class BeforeOrEqual extends DateComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a date before or equal to {$this->date}.";
    }

    protected function compareDates(
        \DateTimeImmutable $valueDate,
        \DateTimeImmutable $referenceDate,
    ): bool {
        return $valueDate <= $referenceDate;
    }
}
