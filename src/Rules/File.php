<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * File Rule - Cost: 10
 */
class File extends BaseRule
{
    public function cost(): int
    {
        return 55;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid uploaded file.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $error = $this->getUploadedFileError($value);
        if ($error !== UPLOAD_ERR_OK) {
            return false;
        }

        $path = $this->getUploadedFilePath($value);
        if ($path !== null) {
            if (is_uploaded_file($path)) {
                return true;
            }

            // Allow local-file fallback only in CLI/phpdbg (tests, synthetic uploads).
            if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                return is_file($path);
            }

            return false;
        }

        return $this->isUploadedFileObject($value);
    }
}
