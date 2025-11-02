<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Missing Rule - Cost: 1
 */
class Missing extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must not be present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return !isset($data[$field]) || $this->isEmpty($value);
    }

}
