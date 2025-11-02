<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * InArray Rule - Cost: 5
 */
class InArray extends BaseRule
{
    protected string $otherField;

    public function __construct(string $otherField)
    {
        $this->otherField = $otherField;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be in {$this->otherField}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return isset($data[$this->otherField])
            && is_array($data[$this->otherField])
            && in_array($value, $data[$this->otherField], true);
    }

}
