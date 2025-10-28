<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ProhibitedIf Rule - Cost: 2
 * Field is prohibited if another field equals a specific value
 */
class ProhibitedIf extends BaseRule
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
        return "The {$field} field is prohibited when {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        // If condition is not met, field is allowed
        if (! isset($data[$this->otherField]) || $data[$this->otherField] !== $this->value) {
            return true;
        }

        // Condition is met, field must be empty
        return $this->isEmpty($value);
    }
}
