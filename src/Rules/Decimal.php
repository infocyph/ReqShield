<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Decimal Rule - Cost: 5
 * Validates that value has specified decimal places
 */
class Decimal extends BaseRule
{
    protected ?int $max;
    protected ?int $min;

    public function __construct(?int $min = null, ?int $max = null)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        if ($this->min && $this->max) {
            return "The {$field} must have between {$this->min} and {$this->max} decimal places.";
        }
        if ($this->min) {
            return "The {$field} must have at least {$this->min} decimal places.";
        }
        if ($this->max) {
            return "The {$field} must have at most {$this->max} decimal places.";
        }
        return "The {$field} must be a decimal number.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $stringValue = (string)$value;

        // Must have a decimal point
        if (!str_contains($stringValue, '.')) {
            return false;
        }

        $parts = explode('.', $stringValue);
        $decimals = strlen($parts[1]);

        if ($this->min !== null && $decimals < $this->min) {
            return false;
        }

        if ($this->max !== null && $decimals > $this->max) {
            return false;
        }

        return true;
    }
}
