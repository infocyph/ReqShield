<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Contracts\Rule;

abstract class BaseRule implements Rule
{
    /**
     * Default implementation - rules are not batchable unless overridden.
     */
    public function isBatchable(): bool
    {
        return false;
    }

    /**
     * Get the size of a value.
     *
     * @param mixed $value
     *
     * @return int|float
     */
    protected function getSize(mixed $value): float|int|string
    {
        // Uploaded file arrays should be measured by file size in KB.
        if (is_array($value) && isset($value['size']) && is_numeric($value['size'])) {
            return (float)$value['size'] / 1024;
        }

        // PSR-7 uploaded files should be measured by size in KB.
        if ($this->isUploadedFileObject($value)) {
            $size = $this->getUploadedFileSize($value);
            if ($size !== null) {
                return (float)$size / 1024;
            }
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value) || is_countable($value)) {
            return count($value);
        }

        return 0;
    }

    /**
     * Resolve client MIME type from uploaded file payload.
     */
    protected function getUploadedFileClientMediaType(mixed $value): ?string
    {
        if (is_array($value) && isset($value['type']) && is_string($value['type'])) {
            return $value['type'];
        }

        if ($this->isUploadedFileObject($value) && method_exists($value, 'getClientMediaType')) {
            $type = $value->getClientMediaType();

            return is_string($type) ? $type : null;
        }

        return null;
    }

    /**
     * Get uploaded file error code for array or object payloads.
     */
    protected function getUploadedFileError(mixed $value): ?int
    {
        if (is_array($value) && isset($value['error']) && is_int($value['error'])) {
            return $value['error'];
        }

        if ($this->isUploadedFileObject($value) && method_exists($value, 'getError')) {
            $error = $value->getError();

            return is_int($error) ? $error : null;
        }

        return null;
    }

    /**
     * Resolve a temporary path for uploaded file content.
     */
    protected function getUploadedFilePath(mixed $value): ?string
    {
        if (is_array($value) && isset($value['tmp_name']) && is_string($value['tmp_name'])) {
            return $value['tmp_name'];
        }

        if (!$this->isUploadedFileObject($value) || !method_exists($value, 'getStream')) {
            return null;
        }

        try {
            $stream = $value->getStream();
        } catch (\Throwable) {
            return null;
        }

        if (!is_object($stream) || !method_exists($stream, 'getMetadata')) {
            return null;
        }

        $uri = $stream->getMetadata('uri');

        return is_string($uri) && $uri !== '' ? $uri : null;
    }

    /**
     * Get uploaded file size for array or object payloads.
     */
    protected function getUploadedFileSize(mixed $value): ?int
    {
        if (is_array($value) && isset($value['size']) && is_numeric($value['size'])) {
            return (int)$value['size'];
        }

        if ($this->isUploadedFileObject($value) && method_exists($value, 'getSize')) {
            $size = $value->getSize();

            return is_int($size) ? $size : (is_numeric($size) ? (int)$size : null);
        }

        return null;
    }

    /**
     * Helper method to check if value is empty.
     *
     * @param mixed $value
     */
    protected function isEmpty(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if ((is_array($value) || is_countable($value)) && count($value) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if a value is a PSR-7 style uploaded file object.
     */
    protected function isUploadedFileObject(mixed $value): bool
    {
        if (!is_object($value)) {
            return false;
        }

        if (
            interface_exists('\Psr\Http\Message\UploadedFileInterface')
            && $value instanceof \Psr\Http\Message\UploadedFileInterface
        ) {
            return true;
        }

        return method_exists($value, 'getError')
            && method_exists($value, 'getSize')
            && method_exists($value, 'getStream');
    }

}
