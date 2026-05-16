<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredUnless Rule - Cost: 2
 * Field is required unless another field equals a specific value
 */
class RequiredUnless extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} field is required unless {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($field, $data);

        return !$this->isEmpty($value);
    }

    #[\Override]
    protected function shouldEvaluateOnMatch(): bool
    {
        return false;
    }
}
