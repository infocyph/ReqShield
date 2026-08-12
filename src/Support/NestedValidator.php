<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Exceptions\InputLimitException;

final class NestedValidator
{
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

            static::expandWildcardSegments(
                $expanded,
                $data,
                $ruleData['segments'],
                [],
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

    /** @param list<string> $captures */
    protected static function bindRuleToken(string $token, array $captures): string
    {
        [$name, $params] = RuleExpressionParser::parse($token);
        if ($params === [] || in_array($name, ['regex', 'not_regex'], true)) {
            return $token;
        }

        foreach ($params as &$parameter) {
            $captureIndex = 0;
            $parameter = preg_replace_callback(
                '/(^|\.)\*(?=\.|$)/',
                static function (array $match) use ($captures, &$captureIndex): string {
                    $capture = $captures[$captureIndex] ?? end($captures);
                    ++$captureIndex;

                    return $match[1] . $capture;
                },
                $parameter,
            ) ?? $parameter;
        }
        unset($parameter);

        return $name . ':' . implode(',', $params);
    }

    /** @param list<string> $targetSegments */
    protected static function bindWildcardDependencies(mixed $definition, string $targetPath, array $targetSegments): mixed
    {
        $captures = [];
        $targetParts = explode('.', $targetPath);
        foreach ($targetSegments as $index => $segment) {
            if (ctype_digit($segment) && isset($targetParts[$index])) {
                $captures[] = $targetParts[$index];
            }
        }

        if ($captures === []) {
            return $definition;
        }

        if (is_string($definition)) {
            $tokens = RuleExpressionParser::splitRules($definition);
            $bound = array_map(
                static fn(string $token): string => static::bindRuleToken($token, $captures),
                $tokens,
            );

            return implode('|', $bound);
        }

        if (!is_array($definition)) {
            return $definition;
        }

        return array_map(
            static fn(mixed $rule): mixed => is_string($rule)
                ? static::bindRuleToken($rule, $captures)
                : $rule,
            $definition,
        );
    }

    /**
     * @param array<string,mixed> $expanded
     * @param list<string> $segments
     * @param list<string> $path
     */
    protected static function expandWildcardSegments(
        array &$expanded,
        mixed $data,
        array $segments,
        array $path,
        mixed $rule,
        int $maxExpansions,
    ): void {
        if ($segments === []) {
            if (count($expanded) >= $maxExpansions) {
                throw new InputLimitException("Maximum wildcard expansion limit of {$maxExpansions} exceeded.");
            }

            $targetPath = implode('.', $path);
            $expanded[$targetPath] = static::bindWildcardDependencies($rule, $targetPath, $path);

            return;
        }

        $segment = $segments[0];
        $remaining = array_slice($segments, 1);

        if ($segment === '*') {
            if (!is_array($data)) {
                return;
            }

            foreach ($data as $key => $value) {
                static::expandWildcardSegments(
                    $expanded,
                    $value,
                    $remaining,
                    [...$path, (string) $key],
                    $rule,
                    $maxExpansions,
                );
            }

            return;
        }

        static::expandWildcardSegments(
            $expanded,
            is_array($data) && array_key_exists($segment, $data) ? $data[$segment] : null,
            $remaining,
            [...$path, $segment],
            $rule,
            $maxExpansions,
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

    /** @param array<int|string,mixed> $data */
    protected static function updateShapeHash(\HashContext $context, array $data): void
    {
        hash_update($context, '{');

        foreach ($data as $key => $value) {
            hash_update($context, 'k:' . $key . ';');

            if (is_array($value)) {
                static::updateShapeHash($context, $value);
            } else {
                hash_update($context, 's;');
            }
        }

        hash_update($context, '}');
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
