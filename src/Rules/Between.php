<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Between Rule - Cost: 3
 * Validates that a value is between min and max.
 */
class Between extends BaseRule
{
    public function __construct(
        protected int|float $min,
        protected int|float $max,
    ) {}

    public function cost(): int
    {
        return 3;
    }

    public function message(string $field): string
    {
        return "The {$field} must be between {$this->min} and {$this->max}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        $size = $this->getSize($value);

        return $size >= $this->min && $size <= $this->max;
    }
}
