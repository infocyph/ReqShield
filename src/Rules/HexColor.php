<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * HexColor Rule - Cost: 15
 */
class HexColor extends AbstractStringPatternRule
{
    public function message(string $field): string
    {
        return "The {$field} must be a valid hex color.";
    }

    protected function pattern(): string
    {
        return '/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
    }
}
