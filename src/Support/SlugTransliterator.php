<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class SlugTransliterator
{
    public static function convert(string $value): string
    {
        if (function_exists('iconv') && defined('ICONV_IMPL') && in_array(ICONV_IMPL, ['glibc', 'libiconv'], true)) {
            if (ICONV_IMPL === 'libiconv') {
                $value = self::normalizeLibiconvLetters($value);
            }

            return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }
        if (function_exists('transliterator_transliterate')) {
            return transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        }

        return $value;
    }

    private static function normalizeLibiconvLetters(string $value): string
    {
        // libiconv can emit accent markers (e.g. é => 'e). Remove only
        // markers generated from letters; preserve caller punctuation.
        return preg_replace_callback('/[^\P{L}\x00-\x7F]/u', static fn(array $match): string => str_replace(
            ["'", '`', '^', '"', '~'],
            '',
            iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $match[0]) ?: $match[0],
        ), $value) ?? $value;
    }
}
