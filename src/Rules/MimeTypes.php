<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MimeTypes Rule - Cost: 30
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
        return 70;
    }

    public function message(string $field): string
    {
        return "The {$field} must have one of these MIME types: "
          . implode(', ', $this->types) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $mime = $this->resolveMimeType($value);
        if ($mime === null) {
            return false;
        }

        return in_array($mime, $this->types, true);
    }

    protected function resolveMimeType(mixed $value): ?string
    {
        $path = $this->getUploadedFilePath($value);
        if (is_string($path)) {
            $detected = $this->detectMimeTypeFromPath($path);
            if ($detected !== null) {
                return $detected;
            }
        }

        $clientMimeType = $this->getUploadedFileClientMediaType($value);

        return is_string($clientMimeType) && $clientMimeType !== ''
            ? $clientMimeType
            : null;
    }

}
