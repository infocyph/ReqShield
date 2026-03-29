<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Support\MimeTypeResolver;

class Mimes extends BaseRule
{
    /**
     * Allowed file extensions
     */
    protected array $types;

    /**
     * Create a new Mimes rule instance
     *
     * @param string ...$types File extensions (without dots)
     */
    public function __construct(string ...$types)
    {
        $this->types = $types;
    }

    /**
     * Clear the MimeTypeResolver cache (useful for testing)
     */
    public static function clearCache(): void
    {
        MimeTypeResolver::clearCache();
    }

    /**
     * Get the cost of this rule
     */
    public function cost(): int
    {
        return 25;
    }

    /**
     * Get the MIME types for given extensions (for debugging/testing)
     *
     * @return array Array of extension => MIME types
     */
    public function getResolvedMimeTypes(): array
    {
        $resolved = [];
        foreach ($this->types as $extension) {
            $resolved[$extension] = MimeTypeResolver::getMimeTypes($extension);
        }
        return $resolved;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name
     */
    public function message(string $field): string
    {
        return "The {$field} must be one of these types: " . implode(
            ', ',
            $this->types,
        ) . '.';
    }

    /**
     * Determine if the validation rule passes
     *
     * @param mixed $value File array from $_FILES
     * @param string $field Field name
     * @param array $data All validation data
     */
    public function passes(mixed $value, string $field, array $data): bool
    {
        $fileMimeType = $this->resolveMimeType($value);
        if ($fileMimeType === null) {
            return false;
        }

        return $this->matchesResolvedMimeType($fileMimeType)
            || in_array($fileMimeType, $this->types, true);
    }

    protected function matchesResolvedMimeType(string $fileMimeType): bool
    {
        foreach ($this->types as $extension) {
            $allowedMimes = MimeTypeResolver::getMimeTypes($extension);
            if (in_array($fileMimeType, $allowedMimes, true)) {
                return true;
            }
        }

        return false;
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
