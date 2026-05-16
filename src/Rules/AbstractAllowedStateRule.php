<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractAllowedStateRule extends BaseRule
{
    /** @return array<int, mixed> */
    abstract protected function allowedStates(): array;

    public function cost(): int
    {
        return 1;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return in_array($value, $this->allowedStates(), true);
    }
}
