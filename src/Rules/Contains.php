<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Contains Rule - Cost: 5
 */
class Contains extends BaseRule
{
    public function __construct(protected mixed $needle) {}

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must contain the specified value.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (is_string($value)) {
            return str_contains($value, (string) $this->needle);
        }

        if (is_array($value)) {
            return in_array($this->needle, $value, true);
        }

        return false;
    }

}
