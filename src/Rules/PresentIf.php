<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * PresentIf Rule - Cost: 2
 */
class PresentIf extends BaseRule
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
        return "The {$field} must be present when {$this->otherField} is {$this->value}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! isset($data[$this->otherField]) || $data[$this->otherField] !== $this->value) {
            return true;
        }

        return isset($data[$field]);
    }
}
