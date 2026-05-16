<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AcceptedIf Rule - Cost: 2
 * Field must be accepted if another field equals a value
 */
class AcceptedIf extends AbstractComparisonStateConditionRule
{
    /** @var list<string|int|bool> */
    private const array ACCEPTED_VALUES = ['yes', 'on', '1', 1, true, 'true'];

    public function message(string $field): string
    {
        return "The {$field} must be accepted when {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    protected function validStates(): array
    {
        return self::ACCEPTED_VALUES;
    }
}
