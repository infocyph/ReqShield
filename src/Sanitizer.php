<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

class Sanitizer
{
    // Common character sets
    private const string ALPHA = 'a-zA-Z';

    private const string ALPHANUMERIC = self::ALPHA . self::NUMERIC;

    private const string FILENAME_CHARS = self::ALPHANUMERIC . '._-';

    private const int MAX_PIPELINE_CACHE = 256;

    private const string NUMERIC = '0-9';

    private const string SLUG_CHARS = self::ALPHANUMERIC . '_-';

    /** @var array<string,list<callable(mixed):mixed>> */
    private static array $pipelineCallables = [];

    // ============================================
    // Alphanumeric Filters
    // ============================================

    public static function alpha(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::ALPHA . ']/', '', $value);
    }

    public static function alphaDash(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::SLUG_CHARS . ']/', '', $value);
    }

    public static function alphanumeric(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::ALPHANUMERIC . ']/', '', $value);
    }

    public static function alphanumericSpace(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace(
            '/[^' . self::ALPHANUMERIC . '\s]/',
            '',
            $value,
        );
    }

    // ============================================
    // Batch Operations
    // ============================================

    /** @param array<int, mixed> $sanitizers */
    public static function apply(mixed $value, array $sanitizers): mixed
    {
        $resolved = self::resolvePipelineCallables(array_values($sanitizers));

        return self::applyCompiled($value, $resolved);
    }

    /** @param list<callable(mixed):mixed> $pipeline */
    public static function applyCompiled(mixed $value, array $pipeline): mixed
    {
        foreach ($pipeline as $callable) {
            $value = $callable($value);
        }

        return $value;
    }

    /** @return array<int|string, mixed> */
    public static function array(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(fn($item) => is_array($item)
          ? self::array($item)
          : (is_string($item) ? self::string($item) : $item), $value);
    }

    // ============================================
    // Encoding & Decoding
    // ============================================

    public static function base64Decode(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $decoded = base64_decode($value, true);

        return $decoded !== false ? $decoded : '';
    }

    public static function base64Encode(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return base64_encode($value);
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    public static function batch(
        array $values,
        string|callable $sanitizer,
    ): array {
        $callable = self::resolveSanitizerCallable($sanitizer);

        if ($callable === null) {
            return $values;
        }

        return array_map($callable, $values);
    }

    // ============================================
    // Basic Type Sanitizers
    // ============================================

    public static function boolean(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                default => false,
            },
            default => (bool) $value,
        };
    }

    // ============================================
    // Case Conversions
    // ============================================

    public static function camelCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace(
            '/[^' . self::ALPHANUMERIC . '\s]/',
            '',
            $value,
        );
        $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        $value = str_replace(' ', '', $value);

        return mb_strtolower(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
          . mb_substr($value, 1, null, 'UTF-8');
    }

    // ============================================
    // Utility Methods
    // ============================================

    public static function clearCache(): void
    {
        self::$pipelineCallables = [];
    }

    /**
     * @param array<int,mixed> $sanitizers
     * @return list<callable(mixed):mixed>
     */
    public static function compile(array $sanitizers): array
    {
        return self::resolvePipelineCallables($sanitizers);
    }

    public static function currency(mixed $value, string $format = 'USD'): float
    {
        if (!is_string($value)) {
            return is_numeric($value) ? (float) $value : 0.0;
        }

        // 1. Remove all non-formatting, non-numeric characters (currency symbols, etc.)
        $value = self::pregReplace('/[^\d.,-]/', '', $value);

        // 2. Normalize based on format
        $value = strtoupper($format) === 'EUR' ? str_replace(
            ['.', ','],
            ['', '.'],
            $value,
        ) : str_replace(',', '', $value);

        // 3. Cast the cleaned string to float
        return (float) $value;
    }

    public static function domain(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove protocol if present
        $value = preg_replace('/^https?:\/\//', '', $value);

        // Remove path, query, and fragment
        $value = explode('/', (string) $value)[0];

        return strtolower($value);
    }

    public static function email(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $sanitized = filter_var($value, FILTER_SANITIZE_EMAIL);

        return $sanitized !== false ? $sanitized : '';
    }

    public static function escapeLike(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return addcslashes($value, '%_');
    }

    public static function filename(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove null bytes (which basename doesn't handle)
        $value = str_replace("\0", '', basename($value));

        // Keep only safe filename characters, replace others with _
        return self::pregReplace(
            '/[^' . self::FILENAME_CHARS . ']/',
            '_',
            $value,
        );
    }

    public static function float(mixed $value): float
    {
        return match (true) {
            is_float($value) => $value,
            is_numeric($value) => (float) $value,
            is_string($value) => (float) filter_var(
                $value,
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION,
            ),
            default => 0.0,
        };
    }

    public static function formatCurrency(
        mixed $value,
        string $currency = 'USD',
        int $decimals = 2,
    ): string {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return match ($currency) {
            'USD' => '$' . number_format($number, $decimals),
            'EUR' => '€' . number_format($number, $decimals, ',', '.'),
            'GBP' => '£' . number_format($number, $decimals),
            default => $currency . ' ' . number_format($number, $decimals),
        };
    }

    public static function htmlDecode(mixed $value): string
    {
        return is_string($value) ? htmlspecialchars_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
        ) : '';
    }

    public static function htmlEncode(mixed $value): string
    {
        return is_string($value) ? htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ) : '';
    }

    public static function integer(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_numeric($value) => (int) $value,
            is_string($value) => (int) filter_var(
                $value,
                FILTER_SANITIZE_NUMBER_INT,
            ),
            default => 0,
        };
    }

    public static function jsonDecode(
        mixed $value,
        bool $associative = true,
    ): mixed {
        if (!is_string($value)) {
            return $associative ? [] : null;
        }

        // Fix: Wrap in try-catch to handle JSON_THROW_ON_ERROR
        try {
            $decoded = json_decode(
                $value,
                $associative,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            // On error, return the default empty value
            return $associative ? [] : null;
        }

        return $decoded;
    }

    public static function jsonEncode(mixed $value): string
    {
        // Fix: Wrap in try-catch to handle JSON_THROW_ON_ERROR
        try {
            $encoded = json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException) {
            return ''; // Return empty string on encode failure
        }

        return $encoded;
    }

    public static function kebabCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace(
            '/[^' . self::ALPHANUMERIC . '\s]/',
            '',
            $value,
        );

        return self::pregReplace('/\s+/', '-', strtolower($value));
    }

    public static function lowercase(mixed $value): string
    {
        return is_string($value) ? mb_strtolower($value, 'UTF-8') : '';
    }

    public static function normalizeWhitespace(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/\s+/', ' ', trim($value));
    }

    // ============================================
    // Numeric & Currency
    // ============================================

    public static function numeric(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^' . self::NUMERIC . ']/', '', $value);
    }

    public static function pascalCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace(
            '/[^' . self::ALPHANUMERIC . '\s]/',
            '',
            $value,
        );

        return str_replace(
            ' ',
            '',
            mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'),
        );
    }

    public static function phone(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return self::pregReplace('/[^0-9+]/', '', $value);
    }

    public static function removeLineBreaks(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return str_replace(["\r\n", "\r", "\n"], ' ', $value);
    }

    public static function sentenceCase(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
          . mb_strtolower(mb_substr($value, 1, null, 'UTF-8'), 'UTF-8');
    }

    // ============================================
    // Slug & Identifiers
    // ============================================

    public static function slug(mixed $value, string $separator = '-'): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Convert to lowercase
        $value = mb_strtolower($value, 'UTF-8');

        // Transliterate unicode to ASCII
        $value = function_exists('iconv')
            ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value)
            : $value;

        // Replace non-alphanumeric with separator
        $value = self::pregReplace('/[^a-z0-9]+/', $separator, $value);

        // Remove leading/trailing separators
        $value = trim($value, $separator);

        // Replace multiple separators with single
        return self::pregReplace(
            '/' . preg_quote($separator, '/') . '+/',
            $separator,
            $value,
        );
    }

    public static function snakeCase(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::pregReplace(
            '/[^' . self::ALPHANUMERIC . '\s]/',
            '',
            $value,
        );

        return self::pregReplace('/\s+/', '_', strtolower($value));
    }

    public static function string(mixed $value): string
    {
        return match (true) {
            is_string($value) => strip_tags(trim($value)),
            is_numeric($value) => (string) $value,
            default => '',
        };
    }

    // ============================================
    // HTML & Tags
    // ============================================

    /**
     * @param string|array<int, string> $allowedTags
     */
    public static function stripTags(
        mixed $value,
        string|array $allowedTags = '',
    ): string {
        if (!is_string($value)) {
            return '';
        }

        $allowedTags = is_array($allowedTags)
            ? self::allowedTagString($allowedTags)
            : $allowedTags;

        return strip_tags($value, $allowedTags);
    }

    public static function stripWhitespace(mixed $value): string
    {
        return is_string($value) ? self::pregReplace('/\s+/', '', $value) : '';
    }

    public static function titleCase(mixed $value): string
    {
        return is_string($value) ? mb_convert_case(
            $value,
            MB_CASE_TITLE,
            'UTF-8',
        ) : '';
    }

    // ============================================
    // Text Processing
    // ============================================

    public static function trim(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    public static function truncate(
        mixed $value,
        int $length,
        string $suffix = '...',
    ): string {
        if (!is_string($value)) {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length, 'UTF-8') . $suffix;
    }

    public static function truncateWords(
        mixed $value,
        int $words,
        string $suffix = '...',
    ): string {
        if (!is_string($value)) {
            return '';
        }

        $wordArray = explode(' ', $value);

        if (count($wordArray) <= $words) {
            return $value;
        }

        return implode(' ', array_slice($wordArray, 0, $words)) . $suffix;
    }

    public static function uppercase(mixed $value): string
    {
        return is_string($value) ? mb_strtoupper($value, 'UTF-8') : '';
    }

    // ============================================
    // URL & Email
    // ============================================

    public static function url(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $sanitized = filter_var($value, FILTER_SANITIZE_URL);

        return $sanitized !== false ? $sanitized : '';
    }

    /** @param array<int, string> $allowedTags */
    protected static function allowedTagString(array $allowedTags): string
    {
        $allowed = array_values(array_filter(
            $allowedTags,
            is_string(...),
        ));

        return $allowed === [] ? '' : '<' . implode('><', $allowed) . '>';
    }

    /** @param array<int, mixed> $sanitizers */
    protected static function pipelineCacheKey(array $sanitizers): ?string
    {
        $parts = [];

        foreach ($sanitizers as $sanitizer) {
            if (!is_string($sanitizer)) {
                return null;
            }

            $parts[] = $sanitizer;
        }

        return serialize($parts);
    }

    protected static function pregReplace(
        string $pattern,
        string $replacement,
        string $subject,
    ): string {
        $result = preg_replace($pattern, $replacement, $subject);

        return $result ?? $subject;
    }

    /**
     * @param array<int, mixed> $sanitizers
     * @return list<callable(mixed):mixed>
     */
    protected static function resolvePipelineCallables(array $sanitizers): array
    {
        $cacheKey = self::pipelineCacheKey($sanitizers);
        if ($cacheKey !== null && isset(self::$pipelineCallables[$cacheKey])) {
            return self::$pipelineCallables[$cacheKey];
        }

        $resolved = [];

        foreach ($sanitizers as $sanitizer) {
            $callable = self::resolveSanitizerCallable($sanitizer);
            if ($callable === null) {
                $name = is_string($sanitizer) ? $sanitizer : get_debug_type($sanitizer);

                throw new \InvalidArgumentException("Unknown sanitizer '{$name}'.");
            }

            $resolved[] = $callable;
        }

        if ($cacheKey !== null) {
            self::$pipelineCallables[$cacheKey] = $resolved;
            if (count(self::$pipelineCallables) > self::MAX_PIPELINE_CACHE) {
                array_shift(self::$pipelineCallables);
            }
        }

        return $resolved;
    }

    protected static function resolveSanitizerCallable(
        mixed $sanitizer,
    ): ?callable {
        if (is_string($sanitizer)) {
            if (method_exists(self::class, $sanitizer)) {
                return static fn(mixed $input): mixed => self::{$sanitizer}($input);
            }

            if (is_callable($sanitizer)) {
                return static fn(mixed $input): mixed => $sanitizer($input);
            }

            return null;
        }

        return is_callable($sanitizer)
            ? static fn(mixed $input): mixed => $sanitizer($input)
            : null;
    }
}
