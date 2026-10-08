<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class NestedValidator
{
    /** @param array<int|string,mixed> $data */
    public static function assertNoConflictingPaths(array $data): void
    {
        $stack = [[$data, '']];
        $seen = [];

        while ($stack !== []) {
            [$current, $prefix] = array_pop($stack);

            foreach ($current as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
                static::rememberInputPath($seen, $path, $value);
                if (is_array($value)) {
                    $stack[] = [$value, $path];
                }
            }
        }
    }

    /**
     * @param array<int|string,mixed> $data
     * @param array<string,array{path:string,segments:list<string>,rule:mixed,is_wildcard:bool}> $parsedRules
     *
     * @return array<string,mixed>
     */
    public static function expandWildcards(
        array $data,
        array $parsedRules,
        int $maxExpansions = 10_000,
    ): array {
        $expanded = [];

        foreach ($parsedRules as $key => $ruleData) {
            if (!$ruleData['is_wildcard']) {
                $expanded[$key] = $ruleData['rule'];

                continue;
            }

            WildcardPath::expandWildcardSegments(
                $expanded,
                $data,
                $ruleData['segments'],
                [],
                $ruleData['segments'],
                $ruleData['rule'],
                $maxExpansions,
            );
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

            [$found, $value] = static::findValue($data, $path);
            if (!$found) {
                continue;
            }

            $flattened[$path] = $value;
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
     * @return array<string,array{path:string,segments:list<string>,rule:mixed,is_wildcard:bool}>
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
        return HashAlgorithm::shapeSignature($data);
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

    /** @param array<int|string,mixed> $array */
    protected static function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        // Check if keys are sequential integers starting from 0
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /** @param array<string,mixed> $seen */
    protected static function rememberInputPath(array &$seen, string $path, mixed $value): void
    {
        if (array_key_exists($path, $seen) && $seen[$path] !== $value) {
            throw new \InvalidArgumentException('Conflicting dotted and nested input representations.');
        }

        $seen[$path] = $value;
    }

    /**
     * @param array<int|string,mixed> $data
     * @return array{0:bool,1:mixed}
     */
    private static function findValue(array $data, string $path): array
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if ($segment === '*' || !is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }
}
