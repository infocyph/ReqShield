<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class RuleExpressionParser
{
    /**
     * @return array{0:string,1:array<int,string>}
     */
    public static function parse(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $rawParams = $parts[1] ?? '';

        if ($rawParams === '') {
            return [$name, []];
        }

        if (in_array($name, ['regex', 'not_regex'], true)) {
            return [$name, [$rawParams]];
        }

        $params = explode(',', $rawParams);

        if ($name !== 'unique') {
            $params = array_values(array_filter(
                $params,
                static fn (string $value): bool => $value !== '',
            ));
        }

        return [$name, $params];
    }
    /**
     * Split a pipe-delimited rule string while preserving regex pipes.
     *
     * @return array<int,string>
     */
    public static function splitRules(string $rules): array
    {
        if ($rules === '') {
            return [];
        }

        $tokens = [];
        $current = '';
        $inRegex = false;
        $regexLocked = false;
        $regexDelimiter = '';
        $escaped = false;
        $inCharacterClass = false;
        $length = strlen($rules);

        for ($index = 0; $index < $length; $index++) {
            $char = $rules[$index];

            if (!$inRegex && $char === '|') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $tokens[] = $trimmed;
                }

                $current = '';
                $regexLocked = false;
                $escaped = false;
                $inCharacterClass = false;
                $regexDelimiter = '';
                continue;
            }

            $current .= $char;

            if (!$inRegex) {
                if (!$regexLocked) {
                    $detectedDelimiter = self::detectRegexDelimiter($current);

                    if ($detectedDelimiter !== null) {
                        $inRegex = true;
                        $regexLocked = true;
                        $regexDelimiter = $detectedDelimiter;
                        $escaped = false;
                        $inCharacterClass = false;
                    }
                }

                continue;
            }

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '[') {
                $inCharacterClass = true;
                continue;
            }

            if ($char === ']' && $inCharacterClass) {
                $inCharacterClass = false;
                continue;
            }

            if (!$inCharacterClass && $char === $regexDelimiter) {
                $inRegex = false;
            }
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $tokens[] = $trimmed;
        }

        return $tokens;
    }

    protected static function detectRegexDelimiter(string $token): ?string
    {
        $prefix = null;

        if (str_starts_with($token, 'regex:')) {
            $prefix = 'regex:';
        } elseif (str_starts_with($token, 'not_regex:')) {
            $prefix = 'not_regex:';
        }

        if ($prefix === null) {
            return null;
        }

        $param = substr($token, strlen($prefix));
        if ($param === '') {
            return null;
        }

        $delimiter = $param[0];

        if (
            ctype_alnum($delimiter)
            || ctype_space($delimiter)
            || $delimiter === '\\'
        ) {
            return null;
        }

        return $delimiter;
    }
}
