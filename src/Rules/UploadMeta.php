<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * UploadMeta Rule - Cost: 12
 * Validates request-level uploaded file metadata shape for arrays/PSR-7 objects.
 */
class UploadMeta extends BaseRule
{
    protected int $maxFilenameLength;

    public function __construct(
        protected ?string $mode = null,
        int|string|null $maxFilenameLength = 255,
    ) {
        $candidate = is_numeric($maxFilenameLength) ? (int) $maxFilenameLength : 255;
        $this->maxFilenameLength = $candidate > 0 ? $candidate : 255;
    }

    public function cost(): int
    {
        return 12;
    }

    public function message(string $field): string
    {
        return "The {$field} must contain valid upload metadata.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (is_array($value)) {
            return $this->validateArrayMetadata($value);
        }

        if ($this->isUploadedFileObject($value)) {
            return $this->validateObjectMetadata($value);
        }

        return false;
    }

    protected function isSafeFilename(string $name): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '' || str_contains($trimmed, "\0")) {
            return false;
        }

        if (preg_match('/[\/\\\\]/', $trimmed) === 1) {
            return false;
        }

        if ($trimmed === '.' || $trimmed === '..') {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            return false;
        }

        return preg_match('/^[^<>:"|?*]+$/', $trimmed) === 1;
    }

    protected function isValidUploadError(int $error): bool
    {
        return in_array(
            $error,
            [
                UPLOAD_ERR_OK,
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
                UPLOAD_ERR_PARTIAL,
                UPLOAD_ERR_NO_FILE,
                UPLOAD_ERR_NO_TMP_DIR,
                UPLOAD_ERR_CANT_WRITE,
                UPLOAD_ERR_EXTENSION,
            ],
            true,
        );
    }

    protected function requiresSuccess(): bool
    {
        return $this->mode === 'success' || $this->mode === 'strict';
    }

    protected function validateArrayMetadata(array $value): bool
    {
        if (!array_key_exists('error', $value) || !is_int($value['error'])) {
            return false;
        }

        $error = $value['error'];
        if (!$this->isValidUploadError($error)) {
            return false;
        }

        if ($this->requiresSuccess() && $error !== UPLOAD_ERR_OK) {
            return false;
        }

        if (!array_key_exists('size', $value) || !is_numeric($value['size']) || (int) $value['size'] < 0) {
            return false;
        }

        if (array_key_exists('tmp_name', $value) && !is_string($value['tmp_name'])) {
            return false;
        }

        if (!array_key_exists('name', $value) || !is_string($value['name'])) {
            return false;
        }

        if (strlen($value['name']) > $this->maxFilenameLength || !$this->isSafeFilename($value['name'])) {
            return false;
        }

        if ($this->requiresSuccess() && (!isset($value['tmp_name']) || !is_string($value['tmp_name']) || trim($value['tmp_name']) === '')) {
            return false;
        }

        if (array_key_exists('type', $value) && !is_string($value['type'])) {
            return false;
        }

        return true;
    }

    protected function validateObjectMetadata(mixed $value): bool
    {
        $error = $this->getUploadedFileError($value);
        if (!is_int($error) || !$this->isValidUploadError($error)) {
            return false;
        }

        if ($this->requiresSuccess() && $error !== UPLOAD_ERR_OK) {
            return false;
        }

        $size = $this->getUploadedFileSize($value);
        if ($size !== null && $size < 0) {
            return false;
        }

        $name = $this->getUploadedFileClientFilename($value);
        if (!is_string($name) || strlen($name) > $this->maxFilenameLength || !$this->isSafeFilename($name)) {
            return false;
        }

        if ($this->requiresSuccess() && $size === null) {
            return false;
        }

        return true;
    }
}
