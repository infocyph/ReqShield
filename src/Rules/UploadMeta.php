<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

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
        $this->consumeRuleContext($value, $field, $data);
        if (is_array($value)) {
            $metadata = [];
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $metadata[$key] = $item;
                }
            }

            return $this->validateArrayMetadata($metadata);
        }

        if ($this->isUploadedFileObject($value)) {
            return $this->validateObjectMetadata($value);
        }

        return false;
    }

    protected function hasSafeName(string $name): bool
    {
        return strlen($name) <= $this->maxFilenameLength
            && $this->isSafeFilename($name);
    }

    /** @param array<string, mixed> $value */
    protected function hasValidArrayShape(array $value): bool
    {
        return $this->hasValidErrorCode($value)
            && $this->hasValidSize($value)
            && $this->hasValidName($value)
            && $this->hasValidOptionalType($value);
    }

    /** @param array<string, mixed> $value */
    protected function hasValidErrorCode(array $value): bool
    {
        return array_key_exists('error', $value) && is_int($value['error']);
    }

    /** @param array<string, mixed> $value */
    protected function hasValidName(array $value): bool
    {
        return array_key_exists('name', $value) && is_string($value['name']);
    }

    /** @param array<string, mixed> $value */
    protected function hasValidOptionalType(array $value): bool
    {
        return !array_key_exists('type', $value) || is_string($value['type']);
    }

    /** @param array<string, mixed> $value */
    protected function hasValidSize(array $value): bool
    {
        return array_key_exists('size', $value)
            && is_numeric($value['size'])
            && (int) $value['size'] >= 0;
    }

    /** @param array<string, mixed> $value */
    protected function hasValidTmpName(array $value): bool
    {
        return isset($value['tmp_name'])
            && is_string($value['tmp_name'])
            && trim($value['tmp_name']) !== '';
    }

    protected function isSafeFilename(string $name): bool
    {
        return $this->isSafeFilenameString($name);
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

    /** @param array<string, mixed> $value */
    protected function validateArrayMetadata(array $value): bool
    {
        if (!$this->hasValidArrayShape($value)) {
            return false;
        }

        $error = $value['error'];
        if (!is_int($error) || !$this->isValidUploadError($error)) {
            return false;
        }

        if ($this->requiresSuccess() && $error !== UPLOAD_ERR_OK) {
            return false;
        }

        if (array_key_exists('tmp_name', $value) && !is_string($value['tmp_name'])) {
            return false;
        }

        $name = $value['name'];
        if (!is_string($name) || !$this->hasSafeName($name)) {
            return false;
        }

        if ($this->requiresSuccess() && !$this->hasValidTmpName($value)) {
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
        if (!is_string($name) || !$this->hasSafeName($name)) {
            return false;
        }

        if ($this->requiresSuccess() && $size === null) {
            return false;
        }

        return true;
    }
}
