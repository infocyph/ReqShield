<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ActiveUrl Rule - Cost: 150
 * Validates that the value is an active URL (DNS check)
 */
class ActiveUrl extends BaseRule
{
    protected const MAX_DNS_CACHE_ENTRIES = 256;

    /**
     * @var array<string,bool>
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
        if (!is_string($value)) {
            return false;
        }

        $url = parse_url($value);

        if (!isset($url['host'])) {
            return false;
        }

        $host = strtolower((string)$url['host']);

        if (isset(self::$dnsCache[$host])) {
            $cached = self::$dnsCache[$host];
            unset(self::$dnsCache[$host]);
            self::$dnsCache[$host] = $cached;

            return $cached;
        }

        $isActive = checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA');
        $this->rememberDnsResult($host, $isActive);

        return $isActive;
    }

    protected function rememberDnsResult(string $host, bool $isActive): void
    {
        self::$dnsCache[$host] = $isActive;

        if (count(self::$dnsCache) <= self::MAX_DNS_CACHE_ENTRIES) {
            return;
        }

        array_shift(self::$dnsCache);
    }
}
