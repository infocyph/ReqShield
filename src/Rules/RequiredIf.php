<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredIf Rule - Cost: 2
 * Field is required if another field equals a specific value
 */
class RequiredIf extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} field is required when {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($field, $data);

        return !$this->isEmpty($value);
    }
}
