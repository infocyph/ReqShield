<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Mac Rule - Cost: 15
 * Value must be a valid MAC address
 */
class Mac extends AbstractFilterVarRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid MAC address.";
    }

    protected function filterType(): int
    {
        return FILTER_VALIDATE_MAC;
    }
}
