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
 * FieldAlias::get('user_email'); // Returns 'Email Address'
 */
class FieldAlias
{
    /**
     * Field name aliases.
     */
    protected static array $aliases = [];

    /**
     * Set field aliases.
     *
     * @param array $aliases Map of field names to display names
     */
    public static function set(array $aliases): void
    {
        static::$aliases = array_merge(static::$aliases, $aliases);
    }

    /**
     * Get field alias or auto-format field name.
     *
     * @param string $field The field name
     * @return string The display name
     */
    public static function get(string $field): string
    {
        if (isset(static::$aliases[$field])) {
            return static::$aliases[$field];
        }

        return static::humanize($field);
    }

    /**
     * Clear all aliases.
     */
    public static function clear(): void
    {
        static::$aliases = [];
    }

    /**
     * Auto-format field name to human-readable format.
     *
     * @param string $field The field name
     * @return string Humanized field name
     */
    protected static function humanize(string $field): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', $field));
    }
}
