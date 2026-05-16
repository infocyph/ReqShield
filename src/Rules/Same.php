<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Same Rule - Cost: 2
 * Validates that a field matches another field.
 */
class Same extends AbstractOtherFieldRule
{
    public function message(string $field): string
    {
        return "The {$field} must match {$this->otherField}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return array_key_exists(
            $this->otherField,
            $data,
        ) && $value === $data[$this->otherField];
    }
}
