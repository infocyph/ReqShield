<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * NestedValidator - Handles validation of nested arrays and complex structures.
 *
 * Supports dot notation for nested validation rules:
 * - 'user.email' => validates $data['user']['email']
 * - 'users.*.email' => validates email for each item in users array
 * - 'addresses.0.city' => validates city in first address
 *
 * IMPROVED: Better performance with reduced array operations
 *
 * @example
 * $rules = [
 *     'user.name' => 'required|string|min:3',
 *     'user.email' => 'required|email',
 *     'contacts.*.email' => 'required|email',
 *     'contacts.*.phone' => 'required|phone'
 * ];
 */
class NestedValidator
{
    /**
     * Expand wildcard rules for array validation.
     * IMPROVED: More efficient array operations
     *
     * @param array $data The data to validate
     * @param array $parsedRules Parsed rules from parseRules()
     * @return array Expanded rules without wildcards
     */
    public static function expandWildcards(array $data, array $parsedRules): array
    {
        $expanded = [];

        foreach ($parsedRules as $key => $ruleData) {
            if (!$ruleData['is_wildcard']) {
                $expanded[$key] = $ruleData['rule'];
                continue;
            }

            // Find the array that needs wildcard expansion
            $wildcardIndex = array_search('*', $ruleData['segments'], true);

            if ($wildcardIndex === false) {
                continue;
            }

            $pathBeforeWildcard = $wildcardIndex > 0
                ? implode('.', array_slice($ruleData['segments'], 0, $wildcardIndex))
                : '';

            $pathAfterWildcard = $wildcardIndex < count($ruleData['segments']) - 1
                ? implode('.', array_slice($ruleData['segments'], $wildcardIndex + 1))
                : '';

            // Get the array to iterate
            $arrayData = $pathBeforeWildcard
                ? static::extractValue($data, $pathBeforeWildcard)
                : $data;

            if (!is_array($arrayData)) {
                continue;
            }

            // Expand wildcard for each array item
            // IMPROVED: Build path more efficiently
            foreach (array_keys($arrayData) as $index) {
                $expandedPath = static::buildExpandedPath(
                    $pathBeforeWildcard,
                    $index,
                    $pathAfterWildcard
                );
                $expanded[$expandedPath] = $ruleData['rule'];
            }
        }

        return $expanded;
    }

    /**
     * Extract nested value from data using dot notation.
     * IMPROVED: Early returns for better performance
     *
     * @param array $data The data array
     * @param string $path Dot notation path
     * @return mixed The value at the path or null
     */
    public static function extractValue(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $data;

        foreach ($segments as $segment) {
            if ($segment === '*') {
                return null; // Wildcard should be handled separately
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Flatten nested data for validation.
     * IMPROVED: Use array_push with spread operator instead of array_merge
     *
     * @param array $data The nested data
     * @param string $prefix Key prefix for recursion
     * @return array Flattened data with dot notation keys
     */
    public static function flattenData(array $data, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : (string)$key;

            if (is_array($value) && !empty($value)) {
                // Check if it's an associative array or indexed array
                if (static::isAssociativeArray($value)) {
                    // Recursive flatten for nested arrays
                    $nested = static::flattenData($value, $newKey);
                    // IMPROVED: More efficient merge
                    foreach ($nested as $nestedKey => $nestedValue) {
                        $flattened[$nestedKey] = $nestedValue;
                    }
                } else {
                    // For indexed arrays, flatten each element
                    $flattened[$newKey] = $value;
                }
            } else {
                $flattened[$newKey] = $value;
            }
        }

        return $flattened;
    }

    /**
     * Check if value exists at a nested path.
     *
     * @param array $data The data array
     * @param string $path Dot notation path
     * @return bool True if value exists
     */
    public static function has(array $data, string $path): bool
    {
        $segments = explode('.', $path);
        $value = $data;

        foreach ($segments as $segment) {
            if ($segment === '*') {
                return false; // Can't check existence with wildcard
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Parse nested rules into a flat structure.
     * IMPROVED: More efficient parsing with reduced operations
     *
     * @param array $rules Validation rules with dot notation
     * @return array Parsed rules structure
     */
    public static function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $key => $rule) {
            $hasWildcard = str_contains($key, '*');
            $hasDot = str_contains($key, '.');

            $parsed[$key] = [
                'path' => $key,
                'segments' => $hasDot ? explode('.', $key) : [$key],
                'rule' => $rule,
                'is_wildcard' => $hasWildcard,
            ];
        }

        return $parsed;
    }

    /**
     * Set a value in nested data using dot notation.
     *
     * @param array $data The data array (passed by reference)
     * @param string $path Dot notation path
     * @param mixed $value Value to set
     */
    public static function setValue(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$data;

        foreach ($segments as $i => $segment) {
            $isLast = $i === count($segments) - 1;

            if ($isLast) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Build expanded path efficiently.
     * IMPROVED: New helper method to reduce string concatenation overhead
     */
    protected static function buildExpandedPath(string $before, int|string $index, string $after): string
    {
        if ($before && $after) {
            return "{$before}.{$index}.{$after}";
        }

        if ($before) {
            return "{$before}.{$index}";
        }

        if ($after) {
            return "{$index}.{$after}";
        }

        return (string)$index;
    }

    /**
     * Check if array is associative.
     * IMPROVED: New helper method for better array handling
     */
    protected static function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
