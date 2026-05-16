<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AfterOrEqual Rule - Cost: 25
 */
class AfterOrEqual extends DateComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a date after or equal to {$this->date}.";
    }

    protected function compareDates(
        \DateTimeImmutable $valueDate,
        \DateTimeImmutable $referenceDate,
    ): bool {
        return $valueDate >= $referenceDate;
    }
}
