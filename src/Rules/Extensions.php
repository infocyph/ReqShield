<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Extensions Rule - Cost: 10
 */
class Extensions extends BaseRule
{
    protected array $extensions;

    public function __construct(string ...$extensions)
    {
        $this->extensions = array_map('strtolower', $extensions);
    }

    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must have one of these extensions: " . implode(', ', $this->extensions) . ".";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_array($value) || !isset($value['name'])) {
            return false;
        }
        $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
        return in_array($ext, $this->extensions, true);
    }
}
