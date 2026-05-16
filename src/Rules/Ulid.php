<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Ulid Rule - Cost: 15
 */
class Ulid extends AbstractStringPatternRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a valid ULID.";
    }

    protected function pattern(): string
    {
        return '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';
    }
}
