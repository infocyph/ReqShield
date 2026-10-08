<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Rules\Distinct;

final class WildcardPath
{
    /**
     * @param array<string,mixed> $expanded
     * @param list<string> $segments
     * @param list<string> $path
     * @param list<string> $schemaSegments
     */
    public static function expandWildcardSegments(
        array &$expanded,
        mixed $data,
        array $segments,
        array $path,
        array $schemaSegments,
        mixed $rule,
        int $maxExpansions,
    ): void {
        if ($segments === []) {
            static::appendExpanded($expanded, $path, $schemaSegments, $rule, $maxExpansions);

            return;
        }

        $segment = $segments[0];
        $remaining = array_slice($segments, 1);
        if ($segment === '*') {
            static::expandWildcardBranch(
                $expanded, $data, $remaining, $path, $schemaSegments, $rule, $maxExpansions,
            );

            return;
        }

        static::expandWildcardSegments(
            $expanded,
            is_array($data) && array_key_exists($segment, $data) ? $data[$segment] : null,
            $remaining,
            [...$path, $segment],
            $schemaSegments,
            $rule,
            $maxExpansions,
        );
    }

    public static function normalizeIndexedField(string $field): string
    {
        return preg_replace('/\.\d+(?=\.|$)/', '.*', $field) ?? $field;
    }

    public static function toRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');

        return '/^' . str_replace('\*', '[^.]+', $escaped) . '$/';
    }

    /** @param array<string,mixed> $expanded
     *  @param list<string> $path
     *  @param list<string> $schemaSegments
     */
    protected static function appendExpanded(
        array &$expanded,
        array $path,
        array $schemaSegments,
        mixed $rule,
        int $maxExpansions,
    ): void {
        if (count($expanded) >= $maxExpansions) {
            throw new InputLimitException("Maximum wildcard expansion limit of {$maxExpansions} exceeded.");
        }

        $target = implode('.', $path);
        $expanded[$target] = static::bindWildcardDependencies($rule, $target, $schemaSegments);
    }

    /** @param list<mixed> $definitions
     *  @param list<string> $captures
     *  @return list<mixed>
     */
    protected static function bindArrayRules(array $definitions, array $captures, string $pattern): array
    {
        return array_map(
            static fn(mixed $rule): mixed => match (true) {
                $rule === 'distinct' => new Distinct($pattern),
                is_string($rule) => static::bindRuleToken($rule, $captures),
                $rule instanceof Distinct => $rule->forPattern($pattern),
                default => $rule,
            },
            $definitions,
        );
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

    /** @param list<string> $captures
     *  @return string|list<mixed>
     */
    protected static function bindStringRules(string $definition, array $captures, string $pattern): string|array
    {
        $tokens = RuleExpressionParser::splitRules($definition);

        if (in_array('distinct', $tokens, true)) {
            return array_map(
                static fn(string $token): mixed => $token === 'distinct'
                    ? new Distinct($pattern)
                    : static::bindRuleToken($token, $captures),
                $tokens,
            );
        }

        return implode('|', array_map(
            static fn(string $token): string => static::bindRuleToken($token, $captures),
            $tokens,
        ));
    }

    /** @param list<string> $schemaSegments */
    protected static function bindWildcardDependencies(mixed $definition, string $targetPath, array $schemaSegments): mixed
    {
        $captures = static::capturesForPath($targetPath, $schemaSegments);
        if ($captures === []) {
            return $definition;
        }

        $pattern = implode('.', $schemaSegments);

        return match (true) {
            is_string($definition) => static::bindStringRules($definition, $captures, $pattern),
            is_array($definition) => static::bindArrayRules($definition, $captures, $pattern),
            default => $definition,
        };
    }

    /** @param list<string> $schemaSegments
     *  @return list<string>
     */
    protected static function capturesForPath(string $targetPath, array $schemaSegments): array
    {
        $captures = [];
        $targetParts = explode('.', $targetPath);

        foreach ($schemaSegments as $index => $segment) {
            if ($segment === '*' && isset($targetParts[$index])) {
                $captures[] = $targetParts[$index];
            }
        }

        return $captures;
    }

    /** @param array<string,mixed> $expanded
     *  @param list<string> $remaining
     *  @param list<string> $path
     *  @param list<string> $schemaSegments
     */
    protected static function expandWildcardBranch(
        array &$expanded,
        mixed $data,
        array $remaining,
        array $path,
        array $schemaSegments,
        mixed $rule,
        int $maxExpansions,
    ): void {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && str_contains($key, '.')) {
                throw new \InvalidArgumentException('Wildcard input keys cannot contain dots.');
            }

            static::expandWildcardSegments(
                $expanded,
                $value,
                $remaining,
                [...$path, (string) $key],
                $schemaSegments,
                $rule,
                $maxExpansions,
            );
        }
    }
}
