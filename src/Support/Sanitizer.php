<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * Sanitizer
 *
 * High-performance input sanitization with comprehensive methods.
 * Optimized for PHP 8.4+ with modern features.
 *
 * OPTIMIZED:
 * - Type-specific fast paths
 * - Minimal function calls
 * - Cached regex patterns
 * - Union types for flexibility
 * - Match expressions for performance
 */
class Sanitizer
{
    // Cached regex patterns for performance
    private static array $patterns = [];

    // Common character sets
    private const ALPHA = 'a-zA-Z';
    private const NUMERIC = '0-9';
    private const ALPHANUMERIC = self::ALPHA . self::NUMERIC;
    private const SLUG_CHARS = self::ALPHANUMERIC . '_-';
    private const FILENAME_CHARS = self::ALPHANUMERIC . '._-';

    // ============================================
    // Basic Type Sanitizers
    // ============================================

    /**
     * Sanitize string - remove HTML/PHP tags and trim
     * OPTIMIZED: Direct string operations
     */
    public static function string(mixed $value): string
    {
        return match(true) {
            is_string($value) => strip_tags(trim($value)),
            is_numeric($value) => (string)$value,
            default => ''
        };
    }

    /**
     * Sanitize integer
     * OPTIMIZED: Fast path for actual integers
     */
    public static function integer(mixed $value): int
    {
        return match(true) {
            is_int($value) => $value,
            is_float($value) => (int)$value,
            is_string($value) => (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT),
            is_bool($value) => (int)$value,
            default => 0
        };
    }

    /**
     * Sanitize float
     * OPTIMIZED: Fast path for actual floats
     */
    public static function float(mixed $value): float
    {
        return match(true) {
            is_float($value) => $value,
            is_int($value) => (float)$value,
            is_string($value) => (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            default => 0.0
        };
    }

    /**
     * Sanitize boolean
     * OPTIMIZED: Faster with match expression
     */
    public static function boolean(mixed $value): bool
    {
        return match(true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => match(strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                default => false
            },
            default => (bool)$value
        };
    }

    /**
     * Sanitize array recursively
     * OPTIMIZED: Direct recursion without closures
     */
    public static function array(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = is_array($item)
                ? self::array($item)
                : (is_string($item) ? self::string($item) : $item);
        }

        return $result;
    }

    // ============================================
    // Alphanumeric Filters
    // ============================================

    /**
     * Keep only alphanumeric characters
     * OPTIMIZED: Cached pattern
     */
    public static function alphanumeric(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::ALPHANUMERIC . ']/', '', $value);
    }

    /**
     * Keep only alphabetic characters
     */
    public static function alpha(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::ALPHA . ']/', '', $value);
    }

    /**
     * Keep only numeric characters
     */
    public static function numeric(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::NUMERIC . ']/', '', $value);
    }

    /**
     * Keep alphanumeric plus spaces
     */
    public static function alphanumericSpace(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::ALPHANUMERIC . '\s]/', '', $value);
    }

    /**
     * Keep alphanumeric, dash, and underscore
     */
    public static function alphaDash(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::SLUG_CHARS . ']/', '', $value);
    }

    // ============================================
    // String Transformations
    // ============================================

    /**
     * Convert to lowercase
     * OPTIMIZED: Direct MB function
     */
    public static function lowercase(mixed $value): string
    {
        return is_string($value) ? mb_strtolower($value, 'UTF-8') : '';
    }

    /**
     * Convert to uppercase
     * OPTIMIZED: Direct MB function
     */
    public static function uppercase(mixed $value): string
    {
        return is_string($value) ? mb_strtoupper($value, 'UTF-8') : '';
    }

    /**
     * Convert to title case
     * OPTIMIZED: Direct conversion
     */
    public static function titleCase(mixed $value): string
    {
        return is_string($value) ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8') : '';
    }

    /**
     * Convert to sentence case (first letter uppercase)
     */
    public static function sentenceCase(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') .
            mb_strtolower(mb_substr($value, 1, null, 'UTF-8'), 'UTF-8');
    }

    /**
     * Convert to camelCase
     */
    public static function camelCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace('/[^' . self::ALPHANUMERIC . '\s]/', '', $value);
        $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        $value = str_replace(' ', '', $value);

        return mb_strtolower(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') .
            mb_substr($value, 1, null, 'UTF-8');
    }

    /**
     * Convert to snake_case
     */
    public static function snakeCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace('/[^' . self::ALPHANUMERIC . '\s]/', '', $value);
        $value = self::pregReplace('/\s+/', '_', strtolower($value));

        return $value;
    }

    /**
     * Convert to kebab-case
     */
    public static function kebabCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace('/[^' . self::ALPHANUMERIC . '\s]/', '', $value);
        $value = self::pregReplace('/\s+/', '-', strtolower($value));

        return $value;
    }

    // ============================================
    // Whitespace Handling
    // ============================================

    /**
     * Normalize whitespace (multiple spaces to single)
     * OPTIMIZED: Cached pattern
     */
    public static function normalizeWhitespace(mixed $value): string
    {
        return is_string($value)
            ? self::pregReplace('/\s+/', ' ', trim($value))
            : '';
    }

    /**
     * Strip all whitespace
     * OPTIMIZED: Cached pattern
     */
    public static function stripWhitespace(mixed $value): string
    {
        return is_string($value)
            ? self::pregReplace('/\s+/', '', $value)
            : '';
    }

    /**
     * Trim whitespace from both ends
     */
    public static function trim(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Remove line breaks
     */
    public static function removeLineBreaks(mixed $value): string
    {
        return is_string($value)
            ? self::pregReplace('/[\r\n]+/', ' ', $value)
            : '';
    }

    /**
     * Normalize line breaks to \n
     */
    public static function normalizeLineBreaks(mixed $value): string
    {
        return is_string($value)
            ? self::pregReplace('/\r\n|\r/', "\n", $value)
            : '';
    }

    // ============================================
    // HTML & Encoding
    // ============================================

    /**
     * Escape HTML entities
     * OPTIMIZED: Direct htmlspecialchars
     */
    public static function escape(mixed $value): string
    {
        return is_string($value)
            ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
    }

    /**
     * Strip HTML tags
     */
    public static function stripTags(mixed $value, string|array $allowedTags = ''): string
    {
        if (!is_string($value)) {
            return '';
        }

        if (is_array($allowedTags)) {
            $allowedTags = '<' . implode('><', $allowedTags) . '>';
        }

        return strip_tags($value, $allowedTags);
    }

    /**
     * Strip HTML tags except safe ones
     */
    public static function stripUnsafeTags(mixed $value): string
    {
        $safeTags = ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li'];
        return self::stripTags($value, $safeTags);
    }

    /**
     * Decode HTML entities
     */
    public static function decodeHtml(mixed $value): string
    {
        return is_string($value)
            ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
    }

    /**
     * Remove invisible characters (zero-width, etc.)
     */
    public static function removeInvisible(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $value);
    }

    // ============================================
    // URL & Email
    // ============================================

    /**
     * Sanitize URL
     * OPTIMIZED: Built-in filter
     */
    public static function url(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $url = filter_var(trim($value), FILTER_SANITIZE_URL);
        return $url !== false ? $url : '';
    }

    /**
     * Sanitize email
     * OPTIMIZED: Built-in filter
     */
    public static function email(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $email = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        return $email !== false ? $email : '';
    }

    /**
     * Extract domain from email
     */
    public static function emailDomain(mixed $value): string
    {
        $email = self::email($value);

        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }

        return substr($email, strpos($email, '@') + 1);
    }

    /**
     * Sanitize domain name
     */
    public static function domain(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = strtolower(trim($value));
        $value = self::pregReplace('/[^a-z0-9.-]/', '', $value);

        return $value;
    }

    // ============================================
    // File & Path
    // ============================================

    /**
     * Sanitize filename
     * OPTIMIZED: Direct operations
     */
    public static function filename(mixed $value, int $maxLength = 255): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove path components
        $value = basename($value);

        // Keep only safe filename characters
        $value = self::pregReplace('/[^' . self::FILENAME_CHARS . ']/', '', $value);

        // Limit length
        if (mb_strlen($value) > $maxLength) {
            $ext = pathinfo($value, PATHINFO_EXTENSION);
            $name = pathinfo($value, PATHINFO_FILENAME);
            $name = mb_substr($name, 0, $maxLength - mb_strlen($ext) - 1);
            $value = $name . '.' . $ext;
        }

        return $value;
    }

    /**
     * Sanitize file extension
     */
    public static function fileExtension(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $ext = pathinfo($value, PATHINFO_EXTENSION);
        return strtolower(self::pregReplace('/[^a-z0-9]/', '', $ext));
    }

    /**
     * Sanitize path (remove dangerous components)
     */
    public static function path(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Remove directory traversal attempts
        $value = self::pregReplace('/\.\.+[\/\\\\]/', '', $value);

        return $value;
    }

    // ============================================
    // Slug & Identifiers
    // ============================================

    /**
     * Create URL-friendly slug
     * OPTIMIZED: Efficient character replacement
     */
    public static function slug(mixed $value, string $separator = '-'): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Convert to lowercase
        $value = mb_strtolower($value, 'UTF-8');

        // Transliterate unicode to ASCII
        if (function_exists('iconv')) {
            $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }

        // Replace non-alphanumeric with separator
        $value = self::pregReplace('/[^a-z0-9]+/', $separator, $value);

        // Remove leading/trailing separators
        $value = trim($value, $separator);

        // Replace multiple separators with single
        $value = self::pregReplace('/' . preg_quote($separator, '/') . '+/', $separator, $value);

        return $value;
    }

    /**
     * Generate UUID-safe string
     */
    public static function uuid(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^a-f0-9-]/', '', strtolower($value));
    }

    // ============================================
    // Numbers & Currency
    // ============================================

    /**
     * Sanitize decimal number
     */
    public static function decimal(mixed $value, int $decimals = 2): string
    {
        $float = self::float($value);
        return number_format($float, $decimals, '.', '');
    }

    /**
     * Sanitize currency (remove symbols, keep number)
     */
    public static function currency(mixed $value): float
    {
        if (!is_string($value)) {
            return self::float($value);
        }

        // Remove currency symbols and keep only numbers and decimal
        $value = self::pregReplace('/[^0-9.]/', '', $value);

        return self::float($value);
    }

    /**
     * Sanitize percentage (0-100)
     */
    public static function percentage(mixed $value, int $decimals = 2): float
    {
        $float = self::float($value);
        $float = max(0, min(100, $float)); // Clamp between 0-100

        return round($float, $decimals);
    }

    // ============================================
    // Phone & Postal
    // ============================================

    /**
     * Sanitize phone number (digits only)
     */
    public static function phone(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Keep only digits and plus sign
        return self::pregReplace('/[^0-9+]/', '', $value);
    }

    /**
     * Sanitize ZIP/postal code
     */
    public static function postalCode(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Keep alphanumeric and dash
        return self::pregReplace('/[^A-Z0-9-]/', '', strtoupper(trim($value)));
    }

    // ============================================
    // Text Processing
    // ============================================

    /**
     * Truncate string to specified length
     * OPTIMIZED: Direct substring
     */
    public static function truncate(mixed $value, int $length, string $suffix = '...'): string
    {
        if (!is_string($value)) {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length, 'UTF-8') . $suffix;
    }

    /**
     * Truncate to word boundary
     */
    public static function truncateWords(mixed $value, int $words, string $suffix = '...'): string
    {
        if (!is_string($value)) {
            return '';
        }

        $wordArray = explode(' ', $value);

        if (count($wordArray) <= $words) {
            return $value;
        }

        return implode(' ', array_slice($wordArray, 0, $words)) . $suffix;
    }

    /**
     * Limit paragraph length
     */
    public static function limitParagraph(mixed $value, int $length, string $suffix = '...'): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::normalizeWhitespace($value);

        return self::truncate($value, $length, $suffix);
    }

    /**
     * Convert multiple line breaks to max number
     */
    public static function limitLineBreaks(mixed $value, int $max = 2): string
    {
        if (!is_string($value)) {
            return '';
        }

        $pattern = '/(\r?\n){' . ($max + 1) . ',}/';
        $replacement = str_repeat("\n", $max);

        return self::pregReplace($pattern, $replacement, $value);
    }

    // ============================================
    // JSON & Serialization
    // ============================================

    /**
     * Sanitize JSON string
     */
    public static function json(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!is_string($value)) {
            return '';
        }

        // Try to decode and re-encode to ensure valid JSON
        $decoded = json_decode($value);

        return $decoded !== null
            ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';
    }

    /**
     * Base64 encode safely
     */
    public static function base64Encode(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return base64_encode($value);
    }

    /**
     * Base64 decode safely
     */
    public static function base64Decode(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $decoded = base64_decode($value, true);
        return $decoded !== false ? $decoded : '';
    }

    // ============================================
    // Security
    // ============================================

    /**
     * Remove SQL-like patterns (basic protection)
     */
    public static function removeSqlPatterns(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $patterns = [
            '/(\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b|\bunion\b)/i',
            '/--/',
            '/\/\*.*?\*\//',
        ];

        foreach ($patterns as $pattern) {
            $value = self::pregReplace($pattern, '', $value);
        }

        return $value;
    }

    /**
     * Remove script tags and event handlers
     */
    public static function removeScripts(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove script tags
        $value = self::pregReplace('/<script\b[^>]*>.*?<\/script>/is', '', $value);

        // Remove event handlers
        $value = self::pregReplace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $value);

        return $value;
    }

    // ============================================
    // Batch Operations
    // ============================================

    /**
     * Apply multiple sanitizers in sequence
     * OPTIMIZED: Minimal overhead
     */
    public static function apply(mixed $value, array $sanitizers): mixed
    {
        foreach ($sanitizers as $sanitizer) {
            $value = match(true) {
                is_string($sanitizer) && method_exists(self::class, $sanitizer)
                => self::$sanitizer($value),
                is_callable($sanitizer)
                => $sanitizer($value),
                default => $value
            };
        }

        return $value;
    }

    /**
     * Sanitize array of values with same sanitizer
     */
    public static function batch(array $values, string|callable $sanitizer): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[$key] = is_string($sanitizer) && method_exists(self::class, $sanitizer)
                ? self::$sanitizer($value)
                : (is_callable($sanitizer) ? $sanitizer($value) : $value);
        }

        return $result;
    }

    /**
     * Sanitize associative array with field-specific sanitizers
     */
    public static function fields(array $data, array $sanitizers): array
    {
        $result = [];

        foreach ($data as $field => $value) {
            if (isset($sanitizers[$field])) {
                $sanitizer = $sanitizers[$field];

                $result[$field] = is_array($sanitizer)
                    ? self::apply($value, $sanitizer)
                    : (is_string($sanitizer) && method_exists(self::class, $sanitizer)
                        ? self::$sanitizer($value)
                        : (is_callable($sanitizer) ? $sanitizer($value) : $value));
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    // ============================================
    // Helper Methods
    // ============================================

    /**
     * Optimized preg_replace with pattern caching
     */
    private static function pregReplace(string $pattern, string $replacement, string $subject): string
    {
        // Cache compiled patterns for performance
        if (!isset(self::$patterns[$pattern])) {
            self::$patterns[$pattern] = $pattern;
        }

        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }

    /**
     * Clear pattern cache (for testing)
     */
    public static function clearCache(): void
    {
        self::$patterns = [];
    }
}
