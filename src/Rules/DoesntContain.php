<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DoesntContain - Validates that a string doesn't contain specified substrings
 *
 * FIXED: Now works on strings (was only checking arrays before)
 *
 * Usage:
 *   'field' => 'doesnt_contain:spam,viagra,casino'
 *   'username' => 'doesnt_contain:admin,root,system'
 *
 * This rule ensures the field value (as a string) does not contain
 * any of the specified substrings. Case-sensitive by default.
 */
class DoesntContain extends BaseRule
{
    /**
     * Values that should not be present as substrings
     */
    protected array $values;

    /**
     * Constructor accepts variadic parameters for multiple values
     *
     * @param mixed ...$values Substrings to check for
     */
    public function __construct(mixed ...$values)
    {
        $this->values = $values;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must not contain the specified values.";
    }

    /**
     * Check if value is a string and doesn't contain any of the specified
     * substrings
     *
     * @param mixed $value Field value to validate
     * @param string $field Field name
     * @param array $data All validation data
     *
     * @return bool True if valid (doesn't contain any values), false otherwise
     */
    public function passes(mixed $value, string $field, array $data): bool
    {
        // Must be a string
        if (!is_string($value)) {
            return false;
        }

        // Check that none of the specified values appear as substrings
        // Returns true only if ALL checks pass (value doesn't contain any needle)
        return array_all(
            $this->values,
            fn ($needle) => !str_contains($value, (string)$needle),
        );
    }

}
