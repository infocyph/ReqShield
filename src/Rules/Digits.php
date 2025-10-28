<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Digits Rule - Cost: 5
 */
class Digits extends BaseRule
{
    protected int $length;

    public function __construct(int $length)
    {
        $this->length = $length;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be {$this->length} digits.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_numeric($value) && strlen((string) $value) === $this->length;
    }
}
