<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class LruCache
{
    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $cache
     * @param TKey $key
     * @param TValue $value
     */
    public static function remember(
        array &$cache,
        int $maxEntries,
        int|string $key,
        mixed $value,
    ): void {
        $cache[$key] = $value;

        if (count($cache) <= $maxEntries) {
            return;
        }

        array_shift($cache);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $cache
     * @param TKey $key
     * @return TValue|null
     */
    public static function touch(array &$cache, int|string $key): mixed
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
