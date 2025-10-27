<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * NotRegex Rule - Cost: 20
 */
class NotRegex extends BaseRule
{
    protected string $pattern;
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function cost(): int
    {
        return 20;
    }

    public function message(string $field): string
    {
        return "The {$field} format is invalid (must not match pattern).";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        return preg_match($this->pattern, (string)$value) === 0;
    }
}
