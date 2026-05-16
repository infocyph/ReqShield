<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ProhibitedIf Rule - Cost: 2
 * Field is prohibited if another field equals a specific value
 */
class ProhibitedIf extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} field is prohibited when {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($field, $data);

        return $this->isEmpty($value);
    }
}
