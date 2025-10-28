<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DeclinedIf Rule - Cost: 2
 * Field must be declined if another field equals a value
 */
class DeclinedIf extends BaseRule
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
        return "The {$field} must be declined when {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        // If condition not met, field can be anything
        if (! isset($data[$this->otherField]) || $data[$this->otherField] !== $this->value) {
            return true;
        }

        return in_array($value, ['no', 'off', '0', 0, false, 'false'], true);
    }
}
