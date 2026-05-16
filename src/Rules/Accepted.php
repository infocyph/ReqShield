<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Accepted Rule - Cost: 1
 * Field must be "yes", "on", 1, or true (useful for terms acceptance)
 */
class Accepted extends AbstractAllowedStateRule
{
    public function message(string $field): string
    {
        return "The {$field} must be accepted.";
    }

    protected function allowedStates(): array
    {
        return ['yes', 'on', '1', 1, true, 'true'];
    }
}
