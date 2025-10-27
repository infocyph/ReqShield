<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DoesntEndWith Rule - Cost: 5
 */
class DoesntEndWith extends BaseRule
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
        return "The {$field} must not end with: " . implode(', ', $this->values) . ".";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return array_all($this->values, fn ($suffix) => !str_ends_with($value, $suffix));
    }
}
