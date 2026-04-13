<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Min Rule - Cost: 2
 * Validates minimum value/length.
 */
class Min extends BaseRule
{
    public function __construct(protected $min) {}

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} must be at least {$this->min}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->getSize($value) >= $this->min;
    }

}
