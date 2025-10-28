<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Confirmed Rule - Cost: 2
 * Field must have a matching confirmation field (field_confirmation)
 */
class Confirmed extends BaseRule
{
    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The $field confirmation does not match.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $confirmationField = $field.'_confirmation';

        return isset($data[$confirmationField]) && $value === $data[$confirmationField];
    }
}
