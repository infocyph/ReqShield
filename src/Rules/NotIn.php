<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

class NotIn extends BaseRule
{
    /** @param array<int, mixed> $values */
    public function __construct(protected array $values) {}

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The selected {$field} is invalid.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return !in_array($value, $this->values, true);
    }
}
