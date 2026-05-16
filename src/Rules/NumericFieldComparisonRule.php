<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class NumericFieldComparisonRule extends BaseRule
{
    public function __construct(protected string $otherField) {}

    abstract protected function compareValues(mixed $value, mixed $other): bool;

    public function cost(): int
    {
        return 3;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_numeric($value) || !array_key_exists($this->otherField, $data)) {
            return false;
        }

        return $this->compareValues($value, $data[$this->otherField]);
    }
}
