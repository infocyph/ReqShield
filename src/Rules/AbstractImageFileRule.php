<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractImageFileRule extends BaseRule
{
    private const int MAX_STREAM_BYTES = 16 * 1024 * 1024;

    /** @return array{0:int,1:int,2:int,3:string}|false */
    protected function getImageInfo(mixed $value): array|false
    {
        $error = $this->getUploadedFileError($value);
        if ($error !== null && $error !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($this->isUploadedFileObject($value)) {
            return $this->getImageInfoFromStream($value);
        }

        $path = $this->getUploadedFilePath($value);
        if (!is_string($path) || $path === '' || str_contains($path, "\0")
            || str_contains($path, '://') || !is_file($path)) {
            return false;
        }

        set_error_handler(static fn() => true);

        try {
            return getimagesize($path);
        } catch (\ValueError|\TypeError) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    /** @return array{0:int,1:int,2:int,3:string}|false */
    protected function getImageInfoFromStream(object $value): array|false
    {
        $stream = null;
        $position = null;

        try {
            $stream = $value->getStream();
            if (!is_object($stream) || !method_exists($stream, 'isSeekable')
                || !method_exists($stream, 'tell') || !method_exists($stream, 'seek')
                || !method_exists($stream, 'read') || !method_exists($stream, 'eof')
                || $stream->isSeekable() !== true) {
                return false;
            }

            $position = $stream->tell();
            $stream->seek(0);
            $bytes = $this->readImageStream($stream);
            if ($bytes === null) {
                return false;
            }

            set_error_handler(static fn() => true);
            try {
                return getimagesizefromstring($bytes);
            } finally {
                restore_error_handler();
            }
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_object($stream) && is_int($position)) {
                try {
                    $stream->seek($position);
                } catch (\Throwable) {
                    // A failed host stream cannot guarantee cursor restoration.
                }
            }
        }
    }

    /** @return non-empty-string|null */
    protected function readImageStream(object $stream): ?string
    {
        $bytes = '';
        while (!$stream->eof() && strlen($bytes) < self::MAX_STREAM_BYTES) {
            $length = min(65536, self::MAX_STREAM_BYTES - strlen($bytes));
            $chunk = $stream->read($length);
            if (!is_string($chunk) || $chunk === '') {
                return null;
            }

            $bytes .= $chunk;
        }

        return $bytes !== '' && $stream->eof() ? $bytes : null;
    }
}
