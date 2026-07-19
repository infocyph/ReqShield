<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ProhibitedUnless Rule - Cost: 2
 */
class ProhibitedUnless extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} is prohibited unless {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($field, $data);

        return $this->isEmpty($value);
    }

    #[\Override]
    protected function shouldEvaluateOnMatch(): bool
    {
        return false;
    }
}
