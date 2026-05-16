<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Declined Rule - Cost: 1
 * Field must be "no", "off", 0, or false
 */
class Declined extends AbstractAllowedStateRule
{
    public function message(string $field): string
    {
        return "The {$field} must be declined.";
    }

    protected function allowedStates(): array
    {
        return ['no', 'off', '0', 0, false, 'false'];
    }
}
