<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractStringPatternRule extends BaseRule
{
    abstract protected function pattern(): string;

    public function cost(): int
    {
        return 15;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return is_string($value) && preg_match($this->pattern(), $value) === 1;
    }
}
