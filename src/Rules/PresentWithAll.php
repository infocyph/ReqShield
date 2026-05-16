<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * PresentWithAll Rule - Cost: 2
 */
class PresentWithAll extends AbstractPresentWithRule
{
    public function message(string $field): string
    {
        return "The {$field} must be present when all of {$this->joinedFields()} are present.";
    }

    protected function requiresAllFields(): bool
    {
        return true;
    }
}
