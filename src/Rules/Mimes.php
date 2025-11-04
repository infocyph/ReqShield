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
     * Get the cost of this rule
     *
     * @return int
     */
    public function cost(): int
    {
        return 15;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name
     * @return string
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
     * @return bool
     */
    public function passes(mixed $value, string $field, array $data): bool
    {
        // Ensure value is a valid file array with MIME type
        if (!is_array($value) || !isset($value['type'])) {
            return false;
        }

        $fileMimeType = $value['type'];

        // Check each extension provided
        foreach ($this->types as $extension) {
            // Get allowed MIME types for this extension using MimeTypeResolver
            // This only loads the category needed (lazy loading)
            $allowedMimes = MimeTypeResolver::getMimeTypes($extension);

            // Check if file's MIME type matches any of the allowed MIME types
            if (in_array($fileMimeType, $allowedMimes, true)) {
                return true;
            }
        }

        // If extension is not found in resolver, treat as literal MIME type
        // This provides backward compatibility
        return in_array($fileMimeType, $this->types, true);
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
     * Clear the MimeTypeResolver cache (useful for testing)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        MimeTypeResolver::clearCache();
    }
}
