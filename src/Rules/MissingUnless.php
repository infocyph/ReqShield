<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MissingUnless Rule - Cost: 2
 */
class MissingUnless extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} must not be present unless {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return !array_key_exists($field, $data);
    }

    #[\Override]
    protected function shouldEvaluateOnMatch(): bool
    {
        return false;
    }
}
