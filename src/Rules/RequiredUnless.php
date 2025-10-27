<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredUnless Rule - Cost: 2
 * Field is required unless another field equals a specific value
 */
class RequiredUnless extends BaseRule
{
    public function __construct(
        protected string $otherField,
        protected mixed $value
    ) {
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required unless {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        // If condition is met, field is not required
        if (isset($data[$this->otherField]) && $data[$this->otherField] === $this->value) {
            return true;
        }

        // Condition is not met, field must have value
        return !$this->isEmpty($value);
    }
}
