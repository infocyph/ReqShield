<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractCaseTransformRule extends BaseRule
{
    abstract protected function transform(string $value): string;

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_string($value)) {
            return false;
        }

        return $value === $this->transform($value);
    }
}
