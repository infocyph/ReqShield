<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * BeforeOrEqual Rule - Cost: 25
 */
class BeforeOrEqual extends BaseRule
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
        return "The $field must be a date before or equal to $this->date.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        try {
            return new \DateTime((string) $value) <= new \DateTime($this->date);
        } catch (\Exception) {
            return false;
        }
    }
}
