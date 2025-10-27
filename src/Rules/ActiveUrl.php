<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ActiveUrl Rule - Cost: 150
 * Validates that the value is an active URL (DNS check)
 */
class ActiveUrl extends BaseRule
{
    public function cost(): int
    {
        return 150; // Expensive - requires DNS lookup
    }

    public function message(string $field): string
    {
        return "The {$field} must be an active URL.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $url = parse_url($value);

        if (! isset($url['host'])) {
            return false;
        }

        // Check if host has DNS record
        return checkdnsrr($url['host'], 'A') || checkdnsrr($url['host'], 'AAAA');
    }
}
