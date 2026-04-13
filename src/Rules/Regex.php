<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Regex Rule - Cost: 20
 * Validates that a value matches a regular expression.
 */
class Regex extends BaseRule
{
    public function __construct(protected string $pattern) {}

    public function cost(): int
    {
        return 20;
    }

    public function message(string $field): string
    {
        return "The {$field} format is invalid.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match($this->pattern, $value) === 1;
    }

}
