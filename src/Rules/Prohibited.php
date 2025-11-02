<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Prohibited Rule - Cost: 1
 * Field must not be present
 */
class Prohibited extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} field is prohibited.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->isEmpty($value);
    }

}
