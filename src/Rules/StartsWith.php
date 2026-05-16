<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * StartsWith Rule - Cost: 5
 * String must start with one of the given values
 */
class StartsWith extends AbstractAffixValuesRule
{
    public function message(string $field): string
    {
        return "The {$field} must start with one of: {$this->joinedValues()}.";
    }

    protected function matchesAffix(string $value, string $affix): bool
    {
        return str_starts_with($value, $affix);
    }

    protected function useAnyMatch(): bool
    {
        return true;
    }
}
