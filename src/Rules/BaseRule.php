<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Support\ValueStringifier;

abstract class BaseRule implements Rule
{
    public function isBatchable(): bool
    {
        return false;
    }

    protected function consumeRuleContext(
        mixed $value = null,
        mixed $field = null,
        mixed $data = null,
    ): void {}

    protected function detectMimeTypeFromPath(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $detected = $this->detectMimeTypeUsingFileinfo($path);
        if ($detected !== null) {
            return $detected;
        }

        return $this->detectMimeTypeUsingContentType($path);
    }

    protected function detectMimeTypeUsingContentType(string $path): ?string
    {
        if (!function_exists('mime_content_type')) {
            return null;
        }

        $detected = mime_content_type($path);

        return is_string($detected) && $detected !== '' ? $detected : null;
    }

    protected function detectMimeTypeUsingFileinfo(string $path): ?string
    {
        if (!function_exists('finfo_open') || !defined('FILEINFO_MIME_TYPE')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $detected = finfo_file($finfo, $path);

        return is_string($detected) && $detected !== '' ? $detected : null;
    }

    protected function getSize(mixed $value): float|int|string
    {
        // Uploaded file arrays should be measured by file size in KB.
        if (is_array($value) && isset($value['size']) && is_numeric($value['size'])) {
            return (float) $value['size'] / 1024;
        }

        // PSR-7 uploaded files should be measured by size in KB.
        if ($this->isUploadedFileObject($value)) {
            $size = $this->getUploadedFileSize($value);
            if ($size !== null) {
                return (float) $size / 1024;
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

    protected function getUploadedFileClientFilename(mixed $value): ?string
    {
        return is_array($value)
            ? $this->arrayStringValue($value, 'name')
            : $this->uploadedFileObjectStringValue($value, 'getClientFilename');
    }

    protected function getUploadedFileClientMediaType(mixed $value): ?string
    {
        return is_array($value)
            ? $this->arrayStringValue($value, 'type')
            : $this->uploadedFileObjectStringValue($value, 'getClientMediaType');
    }

    protected function getUploadedFileError(mixed $value): ?int
    {
        if (is_array($value) && isset($value['error']) && is_int($value['error'])) {
            return $value['error'];
        }

        if (is_object($value) && $this->isUploadedFileObject($value) && method_exists($value, 'getError')) {
            $error = $value->getError();

            return is_int($error) ? $error : null;
        }

        return null;
    }

    protected function getUploadedFilePath(mixed $value): ?string
    {
        if (is_array($value) && isset($value['tmp_name']) && is_string($value['tmp_name'])) {
            return $value['tmp_name'];
        }

        if (!is_object($value) || !$this->isUploadedFileObject($value) || !method_exists($value, 'getStream')) {
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

    protected function getUploadedFileSize(mixed $value): ?int
    {
        if (is_array($value) && isset($value['size']) && is_numeric($value['size'])) {
            return (int) $value['size'];
        }

        if (is_object($value) && $this->isUploadedFileObject($value) && method_exists($value, 'getSize')) {
            $size = $value->getSize();

            return is_int($size) ? $size : (is_numeric($size) ? (int) $size : null);
        }

        return null;
    }

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

    protected function isNullOrBlankString(mixed $value): bool
    {
        return is_null($value) || (is_string($value) && trim($value) === '');
    }

    protected function isSafeFilenameString(string $name): bool
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

    protected function isUploadedFileObject(mixed $value): bool
    {
        if (!is_object($value)) {
            return false;
        }

        if (
            interface_exists(\Psr\Http\Message\UploadedFileInterface::class)
            && $value instanceof \Psr\Http\Message\UploadedFileInterface
        ) {
            return true;
        }

        return method_exists($value, 'getError')
            && method_exists($value, 'getSize')
            && method_exists($value, 'getStream');
    }

    protected function stringifyValue(mixed $value): string
    {
        return ValueStringifier::stringify($value);
    }

    /** @param array<array-key, mixed> $value */
    private function arrayStringValue(array $value, string $key): ?string
    {
        return isset($value[$key]) && is_string($value[$key])
            ? $value[$key]
            : null;
    }

    private function uploadedFileObjectStringValue(mixed $value, string $method): ?string
    {
        if (!is_object($value) || !$this->isUploadedFileObject($value) || !method_exists($value, $method)) {
            return null;
        }

        $result = $value->{$method}();

        return is_string($result) ? $result : null;
    }
}
