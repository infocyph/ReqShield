<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Present Rule - Cost: 1
 */
class Present extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} must be present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return isset($data[$field]);
    }

}
