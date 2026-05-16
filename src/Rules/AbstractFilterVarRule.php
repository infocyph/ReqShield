<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractFilterVarRule extends BaseRule
{
    abstract protected function filterType(): int;

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, $this->filterType()) !== false;
    }
}
