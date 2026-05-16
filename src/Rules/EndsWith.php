<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * EndsWith Rule - Cost: 5
 * String must end with one of the given values
 */
class EndsWith extends AbstractAffixValuesRule
{
    public function message(string $field): string
    {
        return "The {$field} must end with one of: {$this->joinedValues()}.";
    }

    protected function matchesAffix(string $value, string $affix): bool
    {
        return str_ends_with($value, $affix);
    }

    protected function useAnyMatch(): bool
    {
        return true;
    }
}
