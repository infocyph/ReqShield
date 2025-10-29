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

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if ((is_array($value) || is_countable($value)) && count($value) === 0) {

            // Check for uploaded files in $_FILES superglobal
            if (isset($_FILES[$field])) {
                $file = $_FILES[$field];

                // Check if file was actually uploaded
                if (isset($file['error'])) {
                    return $file['error'] === UPLOAD_ERR_OK;
                }

                // Check if file has content
                return isset($file['size']) && $file['size'] > 0;
            }

            // Check for objects with __toString method
            if (is_object($value) && method_exists($value, '__toString')) {
                $stringValue = (string) $value;
                return $stringValue !== '' && trim($stringValue) !== '';
            }

            // Check for stream resources
            if (is_resource($value)) {
                return get_resource_type($value) === 'stream';
            }

            return false;
        }

        return true;
    }
}
