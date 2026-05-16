<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DoesntEndWith Rule - Cost: 5
 */
class DoesntEndWith extends AbstractAffixValuesRule
{
    public function message(string $field): string
    {
        return "The {$field} must not end with: {$this->joinedValues()}.";
    }

    protected function matchesAffix(string $value, string $affix): bool
    {
        return !str_ends_with($value, $affix);
    }

    protected function useAnyMatch(): bool
    {
        return false;
    }
}
