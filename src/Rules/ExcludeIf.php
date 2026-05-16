<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ExcludeIf Rule - Cost: 2
 */
class ExcludeIf extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} will be excluded when {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($value, $field, $data);

        return false;
    }
}
