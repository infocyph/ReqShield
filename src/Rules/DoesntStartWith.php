<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DoesntStartWith Rule - Cost: 5
 */
class DoesntStartWith extends AbstractAffixValuesRule
{
    public function message(string $field): string
    {
        return "The {$field} must not start with: {$this->joinedValues()}.";
    }

    protected function matchesAffix(string $value, string $affix): bool
    {
        return !str_starts_with($value, $affix);
    }

    protected function useAnyMatch(): bool
    {
        return false;
    }
}
