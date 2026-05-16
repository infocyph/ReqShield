<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DeclinedIf Rule - Cost: 2
 * Field must be declined if another field equals a value
 */
class DeclinedIf extends AbstractComparisonStateConditionRule
{
    /** @var list<string|int|bool> */
    private const array DECLINED_VALUES = ['no', 'off', '0', 0, false, 'false'];

    public function message(string $field): string
    {
        return "The {$field} must be declined when {$this->otherField} is "
            . $this->stringifyValue($this->value) . '.';
    }

    protected function validStates(): array
    {
        return self::DECLINED_VALUES;
    }
}
