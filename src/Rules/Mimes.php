<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Mimes Rule - Cost: 15
 */
class Mimes extends BaseRule
{
    protected array $types;

    public function __construct(string ...$types)
    {
        $this->types = $types;
    }

    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be one of these types: ".implode(', ', $this->types).'.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_array($value) || ! isset($value['type'])) {
            return false;
        }

        return in_array($value['type'], $this->types, true);
    }
}
