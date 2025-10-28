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
     * Parse nested rules into a flat structure.
     *
     * @param array $rules Validation rules with dot notation
     * @return array Parsed rules structure
     */
    public static function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $key => $rule) {
            if (str_contains($key, '.')) {
                $parsed[$key] = [
                    'path' => $key,
                    'segments' => explode('.', $key),
                    'rule' => $rule,
                    'is_wildcard' => str_contains($key, '*')
                ];
            } else {
                $parsed[$key] = [
                    'path' => $key,
                    'segments' => [$key],
                    'rule' => $rule,
                    'is_wildcard' => false
                ];
            }
        }

        return $parsed;
    }

    /**
     * Extract nested value from data using dot notation.
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
     * Expand wildcard rules for array validation.
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
            $wildcardIndex = array_search('*', $ruleData['segments']);
            $pathBeforeWildcard = implode('.', array_slice($ruleData['segments'], 0, $wildcardIndex));
            $pathAfterWildcard = implode('.', array_slice($ruleData['segments'], $wildcardIndex + 1));

            // Get the array to iterate
            $arrayData = $pathBeforeWildcard
                ? static::extractValue($data, $pathBeforeWildcard)
                : $data;

            if (!is_array($arrayData)) {
                continue;
            }

            // Expand wildcard for each array item
            foreach (array_keys($arrayData) as $index) {
                $expandedPath = $pathBeforeWildcard
                    ? "{$pathBeforeWildcard}.{$index}"
                    : (string)$index;

                if ($pathAfterWildcard) {
                    $expandedPath .= ".{$pathAfterWildcard}";
                }

                $expanded[$expandedPath] = $ruleData['rule'];
            }
        }

        return $expanded;
    }

    /**
     * Flatten nested data for validation.
     *
     * @param array $data The nested data
     * @param string $prefix Key prefix for recursion
     * @return array Flattened data with dot notation keys
     */
    public static function flattenData(array $data, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !empty($value)) {
                $flattened = array_merge($flattened, static::flattenData($value, $newKey));
            } else {
                $flattened[$newKey] = $value;
            }
        }

        return $flattened;
    }
}
