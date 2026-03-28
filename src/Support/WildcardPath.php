<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class WildcardPath
{
    public static function normalizeIndexedField(string $field): string
    {
        return preg_replace('/\.\d+(?=\.|$)/', '.*', $field) ?? $field;
    }

    public static function toRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');

        return '/^' . str_replace('\*', '[^.]+', $escaped) . '$/';
    }
}
