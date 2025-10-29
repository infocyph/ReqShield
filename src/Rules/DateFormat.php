<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DateFormat Rule - Cost: 25
 * Validates that a value matches a specific date format.
 */
class DateFormat extends BaseRule
{
    protected string $format;

    public function __construct(string $format)
    {
        $this->format = $format;
    }

    public function cost(): int
    {
        return 25;
    }

    public function message(string $field): string
    {
        return "The {$field} does not match the format $this->format.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = \DateTime::createFromFormat($this->format, $value);

        return $date && $date->format($this->format) === $value;
    }
}
