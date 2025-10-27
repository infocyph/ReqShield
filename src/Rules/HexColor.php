<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * HexColor Rule - Cost: 15
 */
class HexColor extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid hex color.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_string($value) && preg_match('/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value) === 1;
    }
}
