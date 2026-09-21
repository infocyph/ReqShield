<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class SlugTransliterator
{
    public static function convert(string $value): string
    {
        if (function_exists('iconv') && defined('ICONV_IMPL') && in_array(ICONV_IMPL, ['glibc', 'libiconv'], true)) {
            return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }
        if (function_exists('transliterator_transliterate')) {
            return transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        }

        return $value;
    }
}
