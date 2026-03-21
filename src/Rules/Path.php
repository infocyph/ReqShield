<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Path Rule - Cost: 5
 * Validates filesystem-like path strings.
 */
class Path extends BaseRule
{
    public function __construct(
        protected ?string $mode = null, // absolute|relative|null
    ) {
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid path.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $path = trim($value);
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        $isAbsolute = $this->isAbsolutePath($path);

        return match ($this->mode) {
            'absolute' => $isAbsolute,
            'relative' => !$isAbsolute,
            default => true,
        };
    }

    protected function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\\\');
    }
}
