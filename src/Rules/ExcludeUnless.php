<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ExcludeUnless Rule - Cost: 2
 */
class ExcludeUnless extends AbstractComparisonConditionRule
{
    public function message(string $field): string
    {
        return "The {$field} will be excluded unless {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($value, $field, $data);

        return false;
    }

    #[\Override]
    protected function shouldEvaluateOnMatch(): bool
    {
        return false;
    }
}
