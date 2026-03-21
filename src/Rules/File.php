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
        return 10;
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
            return is_uploaded_file($path) || is_file($path);
        }

        return $this->isUploadedFileObject($value);
    }
}
