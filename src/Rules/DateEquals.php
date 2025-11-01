<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DateEquals Rule - Cost: 25
 */
class DateEquals extends BaseRule
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
        return "The {$field} must be a date equal to {$this->date}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        try {
            return new \DateTime((string)$value)
                    ->format('Y-m-d') ===
                new \DateTime($data[$this->date] ?? $this->date)
                    ->format('Y-m-d');
        } catch (\Exception) {
            return false;
        }
    }

}
