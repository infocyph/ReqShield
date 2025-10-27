<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredArrayKeys Rule - Cost: 5
 */
class RequiredArrayKeys extends BaseRule
{
    protected array $keys;

    public function __construct(string ...$keys)
    {
        $this->keys = $keys;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must have keys: " . implode(', ', $this->keys) . ".";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_array($value)) {
            return false;
        }
        return array_all($this->keys, fn ($key) => array_key_exists($key, $value));
    }
}
