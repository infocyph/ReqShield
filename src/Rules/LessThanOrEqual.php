<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * LessThanOrEqual Rule - Cost: 3
 */
class LessThanOrEqual extends BaseRule
{
    public function __construct(protected string $otherField) {}

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
        if (!is_numeric($value) || !array_key_exists($this->otherField, $data)) {
            return false;
        }

        return $value <= $data[$this->otherField];
    }

}
