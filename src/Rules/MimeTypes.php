<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MimeTypes Rule - Cost: 15
 */
class MimeTypes extends BaseRule
{
    protected array $types;

    public function __construct(string ...$types)
    {
        $this->types = $types;
    }

    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must have one of these MIME types: " .
          implode(', ', $this->types) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $path = $this->getUploadedFilePath($value);
        if ($path === null) {
            $mimeFromClient = $this->getUploadedFileClientMediaType($value);
            return is_string($mimeFromClient)
                && in_array($mimeFromClient, $this->types, true);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (!is_string($mime)) {
            return false;
        }

        return in_array($mime, $this->types, true);
    }

}
