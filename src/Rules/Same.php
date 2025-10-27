<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Same Rule - Cost: 2
 * Validates that a field matches another field.
 */
class Same extends BaseRule
{
    protected string $otherField;

    public function __construct(string $otherField)
    {
        $this->otherField = $otherField;
    }

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
        return isset($data[$this->otherField]) && $value === $data[$this->otherField];
    }
}
