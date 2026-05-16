<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MissingIf Rule - Cost: 2
 */
class MissingIf extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} must not be present when {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        return !array_key_exists($field, $data) || $this->isEmpty($value);
    }
}
