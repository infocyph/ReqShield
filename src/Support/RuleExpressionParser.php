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
                static fn(string $value): bool => $value !== '',
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

        return $tokens;
    }

    /**
     * @param array<int,string> $tokens
     */
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

        return $delimiter;
    }

    protected static function isRuleBoundary(string $char, bool $inRegex): bool
    {
        return $char === '|' && !$inRegex;
    }

    /**
     * @return array{
     *     inRegex: bool,
     *     regexLocked: bool,
     *     regexDelimiter: string,
     *     escaped: bool,
     *     inCharacterClass: bool
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

    /**
     * @param array{
     *     inRegex: bool,
     *     regexLocked: bool,
     *     regexDelimiter: string,
     *     escaped: bool,
     *     inCharacterClass: bool
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
     *     inRegex: bool,
     *     regexLocked: bool,
     *     regexDelimiter: string,
     *     escaped: bool,
     *     inCharacterClass: bool
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
