<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * SafeFilename Rule - Cost: 8
 * Validates a client-supplied filename for path traversal and control characters.
 */
class SafeFilename extends AbstractTrimmedStringRule
{
    public function cost(): int
    {
        return 8;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a safe file name.";
    }

    protected function passesNormalized(string $value): bool
    {
        return $this->isSafeFilenameString($value);
    }
}
