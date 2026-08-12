<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Decimal Rule - Cost: 5
 * Validates that value has specified decimal places
 */
class Decimal extends BaseRule
{
    public function __construct(protected ?int $min = null, protected ?int $max = null)
    {
        if ($this->min !== null && $this->min < 0) {
            throw new \InvalidArgumentException('Minimum decimal places must be zero or greater.');
        }

        if ($this->max !== null && ($this->max < 0 || ($this->min !== null && $this->max < $this->min))) {
            throw new \InvalidArgumentException('Maximum decimal places must not be less than the minimum.');
        }

        if ($this->min !== null && $this->max === null) {
            $this->max = $this->min;
        }
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        if ($this->min !== null && $this->max !== null && $this->min !== $this->max) {
            return "The {$field} must have between {$this->min} and {$this->max} decimal places.";
        }
        if ($this->min !== null && $this->max === $this->min) {
            return "The {$field} must have exactly {$this->min} decimal places.";
        }

        return "The {$field} must be a decimal number.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_numeric($value)) {
            return false;
        }

        $stringValue = (string) $value;

        $dot = strpos($stringValue, '.');
        $decimals = $dot === false ? 0 : strlen(substr($stringValue, $dot + 1));

        if ($this->min !== null && $decimals < $this->min) {
            return false;
        }

        if ($this->max !== null && $decimals > $this->max) {
            return false;
        }

        return true;
    }
}
