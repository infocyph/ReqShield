<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ExcludeUnless Rule - Cost: 2
 */
class ExcludeUnless extends BaseRule
{
    public function __construct(protected string $otherField, protected mixed $value) {}

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} will be excluded unless {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return array_key_exists(
            $this->otherField,
            $data,
        ) && $data[$this->otherField] === $this->value;
    }

}
