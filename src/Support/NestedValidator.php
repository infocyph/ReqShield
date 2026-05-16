<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

class NestedValidator
{
    /**
     * @param array<int|string,mixed> $data
     * @param array<string,array{path:string,segments:array<int,string>,rule:mixed,is_wildcard:bool}> $parsedRules
     *
     * @return array<string,mixed>
     */
    public static function expandWildcards(
        array $data,
        array $parsedRules,
    ): array {
        $expanded = [];

        foreach ($parsedRules as $key => $ruleData) {
            if (!$ruleData['is_wildcard']) {
                $expanded[$key] = $ruleData['rule'];

                continue;
            }

            static::expandWildcardRule($expanded, $data, $ruleData);
        }

        return $expanded;
    }

    /**
     * @param array<int|string,mixed> $data
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
     * @param array<int|string,mixed> $data
     *
     * @return array<string,mixed>
     */
    public static function flattenData(array $data, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                // Keep the original key so array-level rules still work.
                $flattened[$newKey] = $value;

                if (!empty($value)) {
                    // Also flatten nested keys (including indexed arrays) for dot/wildcard rules.
                    $nested = static::flattenData($value, $newKey);
                    foreach ($nested as $nestedKey => $nestedValue) {
                        $flattened[$nestedKey] = $nestedValue;
                    }
                }

                continue;
            }

            $flattened[$newKey] = $value;
        }

        return $flattened;
    }

    /**
     * @param array<int|string,mixed> $data
     * @param array<int,string> $paths
     *
     * @return array<string,mixed>
     */
    public static function flattenForPaths(array $data, array $paths): array
    {
        $flattened = [];

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }

            // Fast path for already-flattened payloads.
            if (array_key_exists($path, $data)) {
                $flattened[$path] = $data[$path];

                continue;
            }

            if (!static::has($data, $path)) {
                continue;
            }

            $flattened[$path] = static::extractValue($data, $path);
        }

        return $flattened;
    }

    /**
     * @param array<int|string,mixed> $data
     */
    public static function getNestedValue(
        array $data,
        string $key,
        mixed $default = null,
    ): mixed {
        // First try direct key access (for flattened arrays)
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        // Then try nested access (for non-flattened arrays)
        $value = static::extractValue($data, $key);

        return $value ?? $default;
    }

    /**
     * @param array<int|string,mixed> $data
     *
     * @return array<int,string>
     */
    public static function getPaths(array $data, string $prefix = ''): array
    {
        $paths = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array(
                $value,
            ) && !empty($value) && static::isAssociativeArray($value)) {
                // Add all nested paths
                $nestedPaths = static::getPaths($value, $newKey);
                foreach ($nestedPaths as $path) {
                    $paths[] = $path;
                }
            } else {
                // Add this path
                $paths[] = $newKey;
            }
        }

        return $paths;
    }

    /**
     * @param array<int|string,mixed> $data
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
     * @param array<int|string,mixed> $rules
     *
     * @return array<string,array{path:string,segments:array<int,string>,rule:mixed,is_wildcard:bool}>
     */
    public static function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $key => $rule) {
            if (!is_string($key)) {
                continue;
            }

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

    /** @param array<int|string,mixed> $data */
    public static function setValue(
        array &$data,
        string $path,
        mixed $value,
    ): void {
        $segments = explode('.', $path);
        $current = &$data;

        foreach ($segments as $i => $segment) {
            $isLast = $i === count($segments) - 1;

            if ($isLast) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array(
                    $current[$segment],
                )) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /** @param array<int|string,mixed> $data */
    public static function shapeSignature(array $data): string
    {
        $context = hash_init(static::resolveShapeHashAlgorithm());
        static::updateShapeHash($context, $data);

        return hash_final($context);
    }

    /**
     * @param array<int|string,mixed> $data
     *
     * @return array<int|string,mixed>
     */
    public static function unflattenData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            static::setValue($result, (string) $key, $value);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $expanded
     * @param array<int|string,mixed> $arrayData
     */
    protected static function appendExpandedWildcardRules(
        array &$expanded,
        array $arrayData,
        string $pathBeforeWildcard,
        string $pathAfterWildcard,
        mixed $rule,
    ): void {
        foreach (array_keys($arrayData) as $index) {
            $expandedPath = static::buildExpandedPath(
                $pathBeforeWildcard,
                $index,
                $pathAfterWildcard,
            );
            $expanded[$expandedPath] = $rule;
        }
    }

    protected static function buildExpandedPath(
        string $before,
        int|string $index,
        string $after,
    ): string {
        if ($before && $after) {
            return "{$before}.{$index}.{$after}";
        }

        if ($before) {
            return "{$before}.{$index}";
        }

        if ($after) {
            return "{$index}.{$after}";
        }

        return (string) $index;
    }

    /**
     * @param array<string,mixed> $expanded
     * @param array<int|string,mixed> $data
     * @param array{path:string,segments:array<int,string>,rule:mixed,is_wildcard:bool} $ruleData
     */
    protected static function expandWildcardRule(
        array &$expanded,
        array $data,
        array $ruleData,
    ): void {
        $paths = static::resolveWildcardPaths($ruleData['segments']);

        if ($paths === null) {
            return;
        }

        $arrayData = static::resolveWildcardArrayData($data, $paths['before']);

        if ($arrayData === null) {
            return;
        }

        static::appendExpandedWildcardRules(
            $expanded,
            $arrayData,
            $paths['before'],
            $paths['after'],
            $ruleData['rule'],
        );
    }

    /** @param array<int|string,mixed> $array */
    protected static function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        // Check if keys are sequential integers starting from 0
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected static function resolveShapeHashAlgorithm(): string
    {
        return HashAlgorithm::require('xxh3');
    }

    /**
     * @param array<int|string,mixed> $data
     *
     * @return array<int|string,mixed>|null
     */
    protected static function resolveWildcardArrayData(
        array $data,
        string $pathBeforeWildcard,
    ): ?array {
        if ($pathBeforeWildcard === '') {
            return $data;
        }

        $value = static::extractValue($data, $pathBeforeWildcard);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<int,string> $segments
     *
     * @return array{before:string,after:string}|null
     */
    protected static function resolveWildcardPaths(array $segments): ?array
    {
        $segments = array_values($segments);
        $wildcardIndex = array_search('*', $segments, true);

        if ($wildcardIndex === false) {
            return null;
        }

        $wildcardIndexInt = (int) $wildcardIndex;
        $before = $wildcardIndex > 0
            ? implode('.', array_slice($segments, 0, $wildcardIndexInt))
            : '';

        $after = $wildcardIndexInt < count($segments) - 1
            ? implode('.', array_slice($segments, $wildcardIndexInt + 1))
            : '';

        return [
            'before' => $before,
            'after' => $after,
        ];
    }

    /** @param array<int|string,mixed> $data */
    protected static function updateShapeHash(\HashContext $context, array $data): void
    {
        hash_update($context, '{');

        $keys = array_keys($data);
        usort($keys, fn($left, $right) => strcmp((string) $left, (string) $right));

        foreach ($keys as $key) {
            $value = $data[$key];
            hash_update($context, 'k:' . $key . ';');

            if (is_array($value)) {
                static::updateShapeHash($context, $value);
            } else {
                hash_update($context, 's;');
            }
        }

        hash_update($context, '}');
    }
}
