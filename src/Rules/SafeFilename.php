<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * SafeFilename Rule - Cost: 8
 * Validates a client-supplied filename for path traversal and control characters.
 */
class SafeFilename extends BaseRule
{
    public function cost(): int
    {
        return 8;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a safe file name.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $name = trim($value);
        if ($name === '' || str_contains($name, "\0")) {
            return false;
        }

        if (preg_match('/[\/\\\\]/', $name) === 1) {
            return false;
        }

        if ($name === '.' || $name === '..') {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return false;
        }

        return preg_match('/^[^<>:"|?*]+$/', $name) === 1;
    }
}
