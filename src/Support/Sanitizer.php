<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * Sanitizer
 *
 * Provides input sanitization methods to clean and normalize data.
 */
class Sanitizer
{
    /**
     * Remove special characters, keep only alphanumeric
     */
    public static function alphanumeric(mixed $value): string
    {
        return is_string($value) ? preg_replace('/[^a-zA-Z0-9]/', '', $value) : '';
    }

    /**
     * Apply multiple sanitizers
     */
    public static function apply(mixed $value, array $sanitizers): mixed
    {
        foreach ($sanitizers as $sanitizer) {
            if (is_string($sanitizer) && method_exists(self::class, $sanitizer)) {
                $value = self::$sanitizer($value);
            } elseif (is_callable($sanitizer)) {
                $value = $sanitizer($value);
            }
        }

        return $value;
    }

    /**
     * Sanitize an array recursively
     */
    public static function array(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(function ($item) {
            if (is_array($item)) {
                return self::array($item);
            }
            return is_string($item) ? self::string($item) : $item;
        }, $value);
    }

    /**
     * Sanitize a boolean
     */
    public static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool)$value;
    }

    /**
     * Sanitize an email address
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
     * Escape HTML entities
     */
    public static function escape(mixed $value): string
    {
        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    }

    /**
     * Sanitize filename
     */
    public static function filename(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove path components
        $value = basename($value);

        // Remove special characters except dots, dashes, underscores
        $value = preg_replace('/[^a-zA-Z0-9._-]/', '', $value);

        return $value;
    }

    /**
     * Sanitize a float
     */
    public static function float(mixed $value): float
    {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitize an integer
     */
    public static function integer(mixed $value): int
    {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Convert to lowercase
     */
    public static function lowercase(mixed $value): string
    {
        return is_string($value) ? mb_strtolower($value) : '';
    }

    /**
     * Normalize whitespace (convert multiple spaces to single space)
     */
    public static function normalizeWhitespace(mixed $value): string
    {
        return is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : '';
    }

    /**
     * Slug: convert to URL-friendly string
     */
    public static function slug(mixed $value, string $separator = '-'): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Convert to lowercase
        $value = mb_strtolower($value);

        // Replace non-alphanumeric characters with separator
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value);

        // Remove leading/trailing separators
        $value = trim($value, $separator);

        return $value;
    }

    /**
     * Sanitize a string by removing HTML/PHP tags
     */
    public static function string(mixed $value): string
    {
        return is_string($value) ? strip_tags(trim($value)) : '';
    }

    /**
     * Strip all whitespace
     */
    public static function stripWhitespace(mixed $value): string
    {
        return is_string($value) ? preg_replace('/\s+/', '', $value) : '';
    }

    /**
     * Convert to title case
     */
    public static function titleCase(mixed $value): string
    {
        return is_string($value) ? mb_convert_case($value, MB_CASE_TITLE) : '';
    }

    /**
     * Truncate string to specified length
     */
    public static function truncate(mixed $value, int $length, string $suffix = '...'): string
    {
        if (!is_string($value)) {
            return '';
        }

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . $suffix;
    }

    /**
     * Convert to uppercase
     */
    public static function uppercase(mixed $value): string
    {
        return is_string($value) ? mb_strtoupper($value) : '';
    }

    /**
     * Sanitize a URL
     */
    public static function url(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $url = filter_var(trim($value), FILTER_SANITIZE_URL);
        return $url !== false ? $url : '';
    }
}
