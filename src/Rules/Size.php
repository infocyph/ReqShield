<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Size Rule - Cost: 2
 * Validates exact size (string length, array count, file size in KB)
 */
class Size extends BaseRule
{
    public function __construct(protected int|float $size)
    {
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} must be exactly {$this->size}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->getSize($value) === $this->size;
    }

}
