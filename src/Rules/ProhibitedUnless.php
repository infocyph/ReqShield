<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ProhibitedUnless Rule - Cost: 2
 */
class ProhibitedUnless extends BaseRule
{
    protected string $otherField;
    protected mixed $value;
    public function __construct(string $otherField, mixed $value)
    {
        $this->otherField = $otherField;
        $this->value = $value;
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} is prohibited unless {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (isset($data[$this->otherField]) && $data[$this->otherField] === $this->value) {
            return true;
        }
        return $this->isEmpty($value);
    }
}
