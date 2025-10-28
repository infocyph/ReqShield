<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Ascii Rule - Cost: 15
 */
class Ascii extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The $field must only contain ASCII characters.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_string($value) && mb_check_encoding($value, 'ASCII');
    }
}
