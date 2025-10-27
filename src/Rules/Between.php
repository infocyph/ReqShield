<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Between Rule - Cost: 3
 * Validates that a value is between min and max.
 */
class Between extends BaseRule
{
    protected $max;
    protected $min;

    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function cost(): int
    {
        return 3;
    }

    public function message(string $field): string
    {
        return "The {$field} must be between {$this->min} and {$this->max}.";
    }

    public function passes($value, string $field, array $data): bool
    {
        $size = $this->getSize($value);
        return $size >= $this->min && $size <= $this->max;
    }
}
