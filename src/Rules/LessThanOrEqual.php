<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * LessThanOrEqual Rule - Cost: 3
 */
class LessThanOrEqual extends BaseRule
{
    protected string $otherField;

    public function __construct(string $otherField)
    {
        $this->otherField = $otherField;
    }

    public function cost(): int
    {
        return 3;
    }

    public function message(string $field): string
    {
        return "The {$field} must be less than or equal to {$this->otherField}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_numeric($value) || ! isset($data[$this->otherField])) {
            return false;
        }

        return $value <= $data[$this->otherField];
    }
}
