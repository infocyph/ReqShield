<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Mimes Rule - Cost: 15
 *
 * Validates file MIME types by mapping file extensions to their corresponding MIME types.
 *
 * Usage:
 *   'document' => 'mimes:pdf,doc,docx'
 *   'photo' => 'mimes:jpg,png,gif'
 *
 * The rule accepts file extensions and automatically maps them to MIME types
 * using the mime-types.php configuration file.
 */
class Mimes extends BaseRule
{
    protected array $types;

    /**
     * Cached MIME type mappings
     * Loaded from mime-types.php configuration file
     */
    protected static ?array $mimeMap = null;

    public function __construct(string ...$types)
    {
        $this->types = $types;

        // Load MIME type mappings if not already loaded
        if (self::$mimeMap === null) {
            self::loadMimeTypes();
        }
    }

    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be one of these types: ".implode(', ', $this->types).'.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_array($value) || ! isset($value['type'])) {
            return false;
        }

        $fileMimeType = $value['type'];

        // Check each extension provided
        foreach ($this->types as $extension) {
            // Get allowed MIME types for this extension
            // If extension not in map, treat it as a literal MIME type (backward compatibility)
            $allowedMimes = self::$mimeMap[strtolower($extension)] ?? [$extension];

            // Check if file's MIME type matches any of the allowed MIME types
            if (in_array($fileMimeType, $allowedMimes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load MIME type mappings from configuration file
     *
     * Looks for mime-types.php in the following locations:
     * 1. Same directory as this class file
     * 2. Project root config directory
     * 3. Package config directory
     */
    protected static function loadMimeTypes(): void
    {
        // Try multiple locations for the mime-types.php file
        $locations = [
            // Same directory as this class
            __DIR__ . '/mime-types.php',

            // Package config directory (assuming standard structure)
            __DIR__ . '/../../config/mime-types.php',

            // Project root config
            dirname(__DIR__, 4) . '/config/mime-types.php',
        ];

        foreach ($locations as $path) {
            if (file_exists($path)) {
                self::$mimeMap = require $path;
                return;
            }
        }
    }

    /**
     * Get the MIME type mapping array (for testing/debugging)
     *
     * @return array
     */
    public static function getMimeMap(): array
    {
        if (self::$mimeMap === null) {
            self::loadMimeTypes();
        }

        return self::$mimeMap;
    }

    /**
     * Clear the cached MIME type mappings (useful for testing)
     *
     * @return void
     */
    public static function clearMimeMap(): void
    {
        self::$mimeMap = null;
    }
}
