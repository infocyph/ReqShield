<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ProhibitedUnless Rule - Cost: 2
 */
class ProhibitedUnless extends BaseRule
{
    public function __construct(protected string $otherField, protected mixed $value) {}

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
        if (array_key_exists($this->otherField, $data) && $data[$this->otherField] === $this->value) {
            return true;
        }
        return $this->isEmpty($value);
    }

}
