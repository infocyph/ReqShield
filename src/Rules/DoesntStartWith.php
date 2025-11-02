<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DoesntStartWith Rule - Cost: 5
 */
class DoesntStartWith extends BaseRule
{
    protected array $values;

    public function __construct(string ...$values)
    {
        $this->values = $values;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must not start with: " . implode(
            ', ',
            $this->values,
        ) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return array_all(
            $this->values,
            fn ($prefix) => !str_starts_with($value, $prefix),
        );
    }

}
