<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * PresentUnless Rule - Cost: 2
 */
class PresentUnless extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} must be present unless {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($value);

        return array_key_exists($field, $data);
    }

    #[\Override]
    protected function shouldEvaluateOnMatch(): bool
    {
        return false;
    }
}
