<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * PresentWith Rule - Cost: 2
 */
class PresentWith extends AbstractPresentWithRule
{
    public function message(string $field): string
    {
        return "The {$field} must be present when any of {$this->joinedFields()} are present.";
    }

    protected function requiresAllFields(): bool
    {
        return false;
    }
}
