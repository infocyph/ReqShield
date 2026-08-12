<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class LruCache
{
    /**
     * @template TValue
     * @param array<string,TValue> $cache
     * @param TValue $value
     */
    public static function remember(
        array &$cache,
        int $maxEntries,
        string $key,
        mixed $value,
    ): void {
        $cache[$key] = $value;

        if (count($cache) <= $maxEntries) {
            return;
        }

        array_shift($cache);
    }

    /**
     * @template TValue
     * @param array<string,TValue> $cache
     * @return TValue|null
     */
    public static function touch(array &$cache, string $key): mixed
    {
        if (!array_key_exists($key, $cache)) {
            return null;
        }

        $value = $cache[$key];
        unset($cache[$key]);
        $cache[$key] = $value;

        return $value;
    }
}
