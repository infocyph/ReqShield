<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Extensions Rule - Cost: 10
 */
class Extensions extends BaseRule
{
    protected array $extensions;

    public function __construct(string ...$extensions)
    {
        $this->extensions = array_map('strtolower', $extensions);
    }

    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must have one of these extensions: " . implode(
            ', ',
            $this->extensions,
        ) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $filename = $this->getUploadedFileClientFilename($value);

        if (!is_string($filename) || $filename === '') {
            $path = $this->getUploadedFilePath($value);
            $filename = is_string($path) ? basename($path) : null;
        }

        if (!is_string($filename) || $filename === '') {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        return in_array($ext, $this->extensions, true);
    }

}
