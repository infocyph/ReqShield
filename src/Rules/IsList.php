<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * IsList Rule - Cost: 5
 */
class IsList extends BaseRule
{
    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a list (sequential array).";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_array($value) && array_is_list($value);
    }

}
