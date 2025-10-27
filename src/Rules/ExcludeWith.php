<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ExcludeWith Rule - Cost: 2
 */
class ExcludeWith extends BaseRule
{
    protected string $otherField;
    public function __construct(string $otherField)
    {
        $this->otherField = $otherField;
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} will be excluded when {$this->otherField} is present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (isset($data[$this->otherField])) {
            return false; // Exclude field
        }
        return true;
    }
}
