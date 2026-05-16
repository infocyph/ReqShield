<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractAffixValuesRule extends StringValuesRule
{
    abstract protected function matchesAffix(string $value, string $affix): bool;

    abstract protected function useAnyMatch(): bool;

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_string($value)) {
            return false;
        }

        $predicate = fn(string $affix): bool => $this->matchesAffix($value, $affix);

        return $this->useAnyMatch()
            ? array_any($this->values, $predicate)
            : array_all($this->values, $predicate);
    }
}
