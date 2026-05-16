<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractComparisonStateConditionRule extends AbstractComparisonConditionRule
{
    /** @return array<int, mixed> */
    abstract protected function validStates(): array;

    /** @param array<array-key, mixed> $data */
    protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool
    {
        unset($field, $data);

        return in_array($value, $this->validStates(), true);
    }
}
