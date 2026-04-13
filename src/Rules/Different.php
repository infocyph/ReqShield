<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Different Rule - Cost: 2
 * Validates that a field is different from another field.
 */
class Different extends BaseRule
{
    public function __construct(protected string $otherField) {}

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} must be different from $this->otherField.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return !array_key_exists(
            $this->otherField,
            $data,
        ) || $value !== $data[$this->otherField];
    }

}
