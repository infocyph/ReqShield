<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredIfDeclined Rule - Cost: 2
 */
class RequiredIfDeclined extends BaseRule
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
        return "The {$field} is required when {$this->otherField} is declined.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $declined = ['no', 'off', '0', 0, false, 'false'];
        if (!isset($data[$this->otherField]) || !in_array($data[$this->otherField], $declined, true)) {
            return true;
        }
        return !$this->isEmpty($value);
    }
}
