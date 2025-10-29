<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Before Rule - Cost: 25
 * Validates that a date is before another date.
 */
class Before extends BaseRule
{
    protected string $date;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function cost(): int
    {
        return 25;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a date before {$this->date}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        try {
            return new \DateTime($value) < new \DateTime($this->date);
        } catch (\Exception $e) {
            return false;
        }
    }
}
