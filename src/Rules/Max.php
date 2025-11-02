<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Max Rule - Cost: 2
 * Validates maximum value/length.
 */
class Max extends BaseRule
{
    protected $max;

    public function __construct($max)
    {
        $this->max = $max;
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} must not exceed {$this->max}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->getSize($value) <= $this->max;
    }

}
