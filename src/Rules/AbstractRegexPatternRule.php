<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractRegexPatternRule extends BaseRule
{
    public function __construct(protected string $pattern) {}

    abstract protected function isPatternResultValid(int|false $result): bool;

    public function cost(): int
    {
        return 20;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return $this->isPatternResultValid(preg_match($this->pattern, (string) $value));
    }
}
