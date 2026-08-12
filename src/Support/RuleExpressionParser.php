<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class RuleExpressionParser
{
    protected const int MAX_PARSED_RULE_CACHE = 1024;

    /** @var array<string,array{0:string,1:array<int,string>}> */
    protected static array $parseCache = [];

    /** @var array<string,array<int,string>> */
    protected static array $splitCache = [];

    public static function clearCache(): void
    {
        self::$parseCache = [];
        self::$splitCache = [];
    }

    /** @return array{0:string,1:array<int,string>} */
    public static function parse(string $rule): array
    {
        $cached = LruCache::touch(self::$parseCache, $rule);
        if (is_array($cached)) {
            return $cached;
        }

        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $rawParams = $parts[1] ?? '';

        if ($rawParams === '') {
            $parsed = [$name, []];
            self::rememberParseCache($rule, $parsed);

            return $parsed;
        }

        if (in_array($name, ['regex', 'not_regex'], true)) {
            $parsed = [$name, [$rawParams]];
            self::rememberParseCache($rule, $parsed);

            return $parsed;
        }

        $params = explode(',', $rawParams);

        if ($name !== 'unique') {
            $params = array_values(array_filter(
                $params,
                static fn(string $value): bool => $value !== '',
            ));
        }

        $parsed = [$name, $params];
        self::rememberParseCache($rule, $parsed);

        return $parsed;
    }

    /** @return array<int,string> */
    public static function splitRules(string $rules): array
    {
        $cached = LruCache::touch(self::$splitCache, $rules);
        if (is_array($cached)) {
            return $cached;
        }

        if ($rules === '') {
            return [];
        }

        $tokens = [];
        $current = '';
        $state = self::newRegexState();
        $length = strlen($rules);

        for ($index = 0; $index < $length; $index++) {
            $char = $rules[$index];

            if (self::isRuleBoundary($char, (bool) $state['inRegex'])) {
                self::appendToken($tokens, $current);
                $current = '';
                $state = self::newRegexState();

                continue;
            }

            $current .= $char;
            self::updateRegexState($state, $current, $char);
        }

        self::appendToken($tokens, $current);

        self::rememberSplitCache($rules, $tokens);

        return $tokens;
    }

    /** @param array<int,string> $tokens */
    protected static function appendToken(array &$tokens, string $current): void
    {
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $tokens[] = $trimmed;
        }
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

        return match ($delimiter) {
            '{' => '}',
            '(' => ')',
            '[' => ']',
            '<' => '>',
            default => $delimiter,
        };
    }

    protected static function isRuleBoundary(string $char, bool $inRegex): bool
    {
        return $char === '|' && !$inRegex;
    }

    /**
     * @return array{
     *   inRegex:bool,
     *   regexLocked:bool,
     *   regexDelimiter:string,
     *   escaped:bool,
     *   inCharacterClass:bool
     * }
     */
    protected static function newRegexState(): array
    {
        return [
            'inRegex' => false,
            'regexLocked' => false,
            'regexDelimiter' => '',
            'escaped' => false,
            'inCharacterClass' => false,
        ];
    }

    /** @param array{0:string,1:array<int,string>} $parsed */
    protected static function rememberParseCache(string $rule, array $parsed): void
    {
        LruCache::remember(
            self::$parseCache,
            self::MAX_PARSED_RULE_CACHE,
            $rule,
            $parsed,
        );
    }

    /** @param array<int,string> $tokens */
    protected static function rememberSplitCache(string $rules, array $tokens): void
    {
        LruCache::remember(
            self::$splitCache,
            self::MAX_PARSED_RULE_CACHE,
            $rules,
            $tokens,
        );
    }

    /**
     * @param array{
     *   inRegex:bool,
     *   regexLocked:bool,
     *   regexDelimiter:string,
     *   escaped:bool,
     *   inCharacterClass:bool
     * } $state
     */
    protected static function tryEnterRegexMode(array &$state, string $current): void
    {
        if ($state['regexLocked']) {
            return;
        }

        $detectedDelimiter = self::detectRegexDelimiter($current);
        if ($detectedDelimiter === null) {
            return;
        }

        $state['inRegex'] = true;
        $state['regexLocked'] = true;
        $state['regexDelimiter'] = $detectedDelimiter;
        $state['escaped'] = false;
        $state['inCharacterClass'] = false;
    }

    /**
     * @param array{
     *   inRegex:bool,
     *   regexLocked:bool,
     *   regexDelimiter:string,
     *   escaped:bool,
     *   inCharacterClass:bool
     * } $state
     */
    protected static function updateRegexState(array &$state, string $current, string $char): void
    {
        if (!$state['inRegex']) {
            self::tryEnterRegexMode($state, $current);

            return;
        }

        if ($state['escaped']) {
            $state['escaped'] = false;

            return;
        }

        if ($char === '\\') {
            $state['escaped'] = true;

            return;
        }

        if ($char === '[') {
            $state['inCharacterClass'] = true;

            return;
        }

        if ($char === ']' && $state['inCharacterClass']) {
            $state['inCharacterClass'] = false;

            return;
        }

        if (!$state['inCharacterClass'] && $char === $state['regexDelimiter']) {
            $state['inRegex'] = false;
        }
    }
}
