<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * AlphaDash Rule - Cost: 15
 * Validates that a value contains only alphanumeric characters, dashes, and
 * underscores.
 */
class AlphaDash extends AbstractStringPatternRule
{
    public function message(string $field): string
    {
        return "The {$field} may only contain letters, numbers, dashes, and underscores.";
    }

    protected function pattern(): string
    {
        return '/^[a-zA-Z0-9_-]+$/';
    }
}
