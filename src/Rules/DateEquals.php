<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DateEquals Rule - Cost: 25
 */
class DateEquals extends DateComparisonRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a date equal to {$this->date}.";
    }

    protected function compareDates(
        \DateTimeImmutable $valueDate,
        \DateTimeImmutable $referenceDate,
    ): bool {
        return $valueDate->format('Y-m-d') === $referenceDate->format('Y-m-d');
    }

    #[\Override]
    /**
     * @param array<array-key, mixed> $data
     */
    protected function resolveReferenceDate(array $data): mixed
    {
        return $data[$this->date] ?? $this->date;
    }
}
