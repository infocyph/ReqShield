<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Same Rule - Cost: 2
 * Validates that a field matches another field.
 */
class Same extends BaseRule
{
    public function __construct(protected string $otherField) {}

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} must match {$this->otherField}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return array_key_exists(
            $this->otherField,
            $data,
        ) && $value === $data[$this->otherField];
    }

}
