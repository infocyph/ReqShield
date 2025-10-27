<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AcceptedIf Rule - Cost: 2
 * Field must be accepted if another field equals a value
 */
class AcceptedIf extends BaseRule
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
        return "The {$field} must be accepted when {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        // If condition not met, field can be anything
        if (!isset($data[$this->otherField]) || $data[$this->otherField] !== $this->value) {
            return true;
        }

        // Condition met, must be accepted
        $acceptable = ['yes', 'on', '1', 1, true, 'true'];
        return in_array($value, $acceptable, true);
    }
}
