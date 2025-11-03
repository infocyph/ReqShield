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
     * Field name aliases storage.
     *
     * @var array<string,string> Associative array where keys are field names
     *   and values are their human-readable aliases
     */
    protected static array $aliases = [];

    /**
     * Retrieves all defined field aliases.
     *
     * @return array<string,string> Associative array of all field aliases
     *   where keys are field names and values are their corresponding display
     *   names
     *
     * @example
     * // Returns ['user_email' => 'Email Address', 'pwd' => 'Password']
     * $aliases = FieldAlias::all();
     */
    public static function all(): array
    {
        return static::$aliases;
    }

    /**
     * Removes all defined field aliases.
     *
     * @return void
     *
     * @example
     * FieldAlias::clear(); // Clears all aliases
     */
    public static function clear(): void
    {
        static::$aliases = [];
    }

    /**
     * Retrieves the alias for a field or generates a human-readable name.
     *
     * If an alias exists for the field, it will be returned. Otherwise,
     * the field name will be automatically humanized.
     *
     * @param string $field The field name to look up
     *
     * @return string The display name (alias if exists, otherwise humanized
     *   field name)
     *
     * @see FieldAlias::humanize() For the auto-formatting logic
     * @example
     * FieldAlias::get('user_email'); // Returns 'Email Address' if alias
     *   exists
     * FieldAlias::get('first_name'); // Returns 'First Name' (auto-humanized)
     *
     */
    public static function get(string $field): string
    {
        return static::$aliases[$field] ?? static::humanize($field);
    }

    /**
     * Retrieves aliases for multiple fields in a single call.
     *
     * More efficient than multiple get() calls when you need aliases for
     * multiple fields.
     *
     * @param string[] $fields Array of field names to look up
     *
     * @return array<string,string> Associative array where keys are field
     *   names
     *                            and values are their corresponding display
     *   names
     *
     * @example
     * $aliases = FieldAlias::getMany(['user_email', 'first_name',
     *   'last_name']);
     * // Returns ['user_email' => 'Email', 'first_name' => 'First Name', ...]
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
     * Checks if an alias exists for the specified field.
     *
     * @param string $field The field name to check
     *
     * @return bool True if an explicit alias exists, false otherwise
     *
     * @example
     * if (FieldAlias::has('user_email')) {
     *     // Custom alias exists for user_email
     * }
     */
    public static function has(string $field): bool
    {
        return isset(static::$aliases[$field]);
    }

    /**
     * Removes a specific field alias.
     *
     * If the field has no alias, this method does nothing.
     *
     * @param string $field The field name whose alias should be removed
     *
     * @return void
     *
     * @example
     * FieldAlias::remove('user_email'); // Removes the alias for user_email
     */
    public static function remove(string $field): void
    {
        unset(static::$aliases[$field]);
    }

    /**
     * Removes multiple field aliases in a single operation.
     *
     * More efficient than multiple remove() calls when you need to remove
     * several aliases. Non-existent field names in the input array are
     * ignored.
     *
     * @param string[] $fields Array of field names whose aliases should be
     *   removed
     *
     * @return void
     *
     * @example
     * FieldAlias::removeMany(['user_email', 'pwd', 'phone']);
     */
    public static function removeMany(array $fields): void
    {
        foreach ($fields as $field) {
            unset(static::$aliases[$field]);
        }
    }

    /**
     * Sets a single field alias or merges multiple aliases.
     *
     * This method provides a flexible way to set one or more field aliases.
     * When called with an array as the first parameter, it merges all aliases
     * at once.
     *
     * @param string|array<string,string> $field Field name (string) or
     *   associative array of field => alias pairs
     * @param string|null $alias Alias name (required if $field is a string)
     *
     * @return void
     *
     * @example
     * // Set a single alias
     * FieldAlias::set('user_email', 'Email Address');
     *
     * // Set multiple aliases at once
     * FieldAlias::set([
     *     'user_email' => 'Email Address',
     *     'pwd' => 'Password',
     *     'phone' => 'Phone Number'
     * ]);
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
     * Sets multiple field aliases in a single batch operation.
     *
     * This method is optimized for setting multiple aliases at once and
     * provides the option to either merge with existing aliases or replace
     * them entirely.
     *
     * @param array<string,string> $aliases Associative array of field => alias
     *   pairs
     * @param bool $replace If true, replaces all existing aliases with the new
     *   set. If false (default), merges new aliases with existing ones.
     *
     * @return void
     *
     * @example
     * // Merge with existing aliases
     * FieldAlias::setBatch([
     *     'user_email' => 'Email Address',
     *     'pwd' => 'Password'
     * ]);
     *
     * // Replace all existing aliases
     * FieldAlias::setBatch([
     *     'first_name' => 'First Name',
     *     'last_name' => 'Last Name'
     * ], true);
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
     * Converts a field name to a human-readable format.
     *
     * This protected method is used internally to automatically generate
     * display names when no explicit alias is set. It replaces underscores,
     * hyphens, and dots with spaces and capitalizes each word.
     *
     * @param string $field The field name to humanize
     *
     * @return string The humanized field name
     *
     * @example
     * // Returns 'First Name'
     * $humanized = FieldAlias::humanize('first_name');
     *
     * // Returns 'User Email'
     * $humanized = FieldAlias::humanize('user-email');
     */
    protected static function humanize(string $field): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', $field));
    }

}
