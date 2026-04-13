<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * UploadId Rule - Cost: 5
 * Validates chunk/session upload identifiers.
 */
class UploadId extends BaseRule
{
    protected int $maxLength;

    public function __construct(int|string|null $maxLength = 128)
    {
        $candidate = is_numeric($maxLength) ? (int) $maxLength : 128;
        $this->maxLength = $candidate > 0 ? $candidate : 128;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid upload id.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $uploadId = trim($value);
        if ($uploadId === '' || strlen($uploadId) > $this->maxLength) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $uploadId) === 1;
    }
}
