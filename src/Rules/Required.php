<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Required Rule - Cost: 1
 * Validates that a value is not empty.
 */
class Required extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (!$this->isEmptyCountable($value)) {
            return true;
        }

        if ($this->hasUploadedFile($field)) {
            return true;
        }

        if ($this->hasNonEmptyStringRepresentation($value)) {
            return true;
        }

        return $this->isStreamResource($value);
    }

    protected function hasNonEmptyStringRepresentation(mixed $value): bool
    {
        if (!is_object($value) || !method_exists($value, '__toString')) {
            return false;
        }

        $stringValue = (string) $value;

        return $stringValue !== '' && trim($stringValue) !== '';
    }

    protected function hasUploadedFile(string $field): bool
    {
        if (!isset($_FILES[$field])) {
            return false;
        }

        $file = $_FILES[$field];
        if (!is_array($file)) {
            return false;
        }

        if (isset($file['error'])) {
            return $file['error'] === UPLOAD_ERR_OK;
        }

        return isset($file['size']) && $file['size'] > 0;
    }

    protected function isEmptyCountable(mixed $value): bool
    {
        return (is_array($value) || is_countable($value)) && count($value) === 0;
    }

    protected function isStreamResource(mixed $value): bool
    {
        if (!is_resource($value)) {
            return false;
        }

        return get_resource_type($value) === 'stream';
    }

}
