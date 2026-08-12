<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Support\LruCache;

/**
 * ActiveUrl Rule - Cost: 150
 * Validates that the value is an active URL (DNS check)
 */
class ActiveUrl extends BaseRule
{
    protected const int DNS_CACHE_TTL_SECONDS = 60;

    protected const MAX_DNS_CACHE_ENTRIES = 256;

    /**
     * @var array<string,array{value:bool,expires_at:int}>
     */
    protected static array $dnsCache = [];

    public static function clearDnsCache(): void
    {
        self::$dnsCache = [];
    }

    public function cost(): int
    {
        return 150;
    }

    public function message(string $field): string
    {
        return "The {$field} must be an active URL.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_string($value)) {
            return false;
        }

        $url = parse_url($value);

        if (!isset($url['host'])) {
            return false;
        }

        $host = strtolower($url['host']);

        $cached = LruCache::touch(self::$dnsCache, $host);
        if (is_array($cached) && $cached['expires_at'] >= time()) {
            return $cached['value'];
        }

        $isActive = checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA');
        $this->rememberDnsResult($host, $isActive);

        return $isActive;
    }

    protected function rememberDnsResult(string $host, bool $isActive): void
    {
        LruCache::remember(
            self::$dnsCache,
            self::MAX_DNS_CACHE_ENTRIES,
            $host,
            ['value' => $isActive, 'expires_at' => time() + self::DNS_CACHE_TTL_SECONDS],
        );
    }
}
