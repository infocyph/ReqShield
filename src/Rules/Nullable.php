<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Nullable Rule - Cost: 1
 * Allows null values (stops validation chain if null)
 */
class Nullable extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        $this->consumeRuleContext($field);

        return '';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        // Always passes
        return true;
    }
}
