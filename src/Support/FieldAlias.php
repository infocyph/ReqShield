<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * FieldAlias - Manages field name aliases for better error messages.
 *
 * Allows mapping technical field names to human-readable names.
 *
 * @example
 * FieldAlias::set(['user_email' => 'Email Address', 'pwd' => 'Password']);
 * FieldAlias::setBatch([
 *     'user_email' => 'Email Address',
 *     'pwd' => 'Password',
 *     'phone_num' => 'Phone Number'
 * ]);
 * FieldAlias::get('user_email'); // Returns 'Email Address'
 */
class FieldAlias
{
    /**
     * Field name aliases.
     */
    protected static array $aliases = [];

    /**
     * Get all defined aliases.
     *
     * @return array All field aliases
     */
    public static function all(): array
    {
        return static::$aliases;
    }

    /**
     * Clear all aliases.
     */
    public static function clear(): void
    {
        static::$aliases = [];
    }

    /**
     * Get field alias or auto-format field name.
     *
     * @param string $field The field name
     *
     * @return string The display name
     */
    public static function get(string $field): string
    {
        return static::$aliases[$field] ?? static::humanize($field);
    }

    /**
     * Get multiple field aliases at once.
     *
     * @param array $fields Array of field names
     *
     * @return array Map of field names to their aliases
     */
    public static function getMany(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = static::get($field);
        }

        return $result;
    }

    /**
     * Check if an alias exists for a field.
     *
     * @param string $field The field name
     *
     * @return bool True if alias exists
     */
    public static function has(string $field): bool
    {
        return isset(static::$aliases[$field]);
    }

    /**
     * Remove a specific alias.
     *
     * @param string $field The field name
     */
    public static function remove(string $field): void
    {
        unset(static::$aliases[$field]);
    }

    /**
     * Remove multiple aliases at once.
     *
     * @param array $fields Array of field names to remove
     */
    public static function removeMany(array $fields): void
    {
        foreach ($fields as $field) {
            unset(static::$aliases[$field]);
        }
    }

    /**
     * Set a single field alias or merge multiple aliases.
     * IMPROVED: Now accepts both single and array format for backward
     * compatibility
     *
     * @param string|array $field Field name or array of aliases
     * @param string|null $alias Alias name (optional if $field is array)
     */
    public static function set(string|array $field, ?string $alias = null): void
    {
        if (is_array($field)) {
            // Batch mode - merge all aliases at once
            static::$aliases = array_merge(static::$aliases, $field);
        } else {
            // Single alias mode
            static::$aliases[$field] = $alias;
        }
    }

    /**
     * Set multiple field aliases at once (batch operation).
     * OPTIMIZED: Single array merge operation
     *
     * @param array $aliases Map of field names to display names
     * @param bool $replace Replace existing aliases instead of merging
     *     (default: false)
     */
    public static function setBatch(array $aliases, bool $replace = false): void
    {
        if ($replace) {
            static::$aliases = $aliases;
        } else {
            static::$aliases = array_merge(static::$aliases, $aliases);
        }
    }

    /**
     * Auto-format field name to human-readable format.
     *
     * @param string $field The field name
     *
     * @return string Humanized field name
     */
    protected static function humanize(string $field): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', $field));
    }

}
