<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * IP Rule - Cost: 10
 * Validates that a value is a valid IP address
 *
 * Supports:
 * - Any IP (IPv4 or IPv6): ip
 * - IPv4 only: ip:v4 or ip:4
 * - IPv6 only: ip:v6 or ip:6
 * - Public IP only: ip:public
 * - Private IP only: ip:private
 * - No reserved ranges: ip:no_res
 * - No private ranges: ip:no_priv
 * - Global range only: ip:global
 * - Combinations: ip:v4,public or ip:v6,no_priv
 *
 * @example
 * 'ip' => 'ip'                    // Any valid IP
 * 'ip' => 'ip:v4'                 // IPv4 only
 * 'ip' => 'ip:v6'                 // IPv6 only
 * 'ip' => 'ip:public'             // Public IP only (no private/reserved)
 * 'ip' => 'ip:private'            // Private IP only
 * 'ip' => 'ip:v4,public'          // Public IPv4 only
 * 'ip' => 'ip:v6,no_priv'         // IPv6 without private ranges
 * 'ip' => 'ip:global'             // Global addresses only
 */
class Ip extends BaseRule
{
    protected int $flags = 0;

    protected bool $ipv4Only = false;

    protected bool $ipv6Only = false;

    protected bool $privateOnly = false;

    protected bool $publicOnly = false;

    /**
     * @param string|null $options Comma-separated options (e.g., "v4,public")
     */
    public function __construct(?string $options = null)
    {
        if ($options !== null && $options !== '') {
            $this->parseOptions($options);
        }

        $this->buildFlags();
    }

    public function cost(): int
    {
        return 10;
    }

    /**
     * Generate contextual error message
     * Complexity: O(1)
     */
    public function message(string $field): string
    {
        // Private IP messages (grouped)
        if ($this->privateOnly) {
            return match (true) {
                $this->ipv4Only => "The {$field} must be a valid private IPv4 address.",
                $this->ipv6Only => "The {$field} must be a valid private IPv6 address.",
                default => "The {$field} must be a valid private IP address.",
            };
        }

        // Public IP messages (grouped)
        if ($this->publicOnly) {
            return match (true) {
                $this->ipv4Only => "The {$field} must be a valid public IPv4 address.",
                $this->ipv6Only => "The {$field} must be a valid public IPv6 address.",
                default => "The {$field} must be a valid public IP address.",
            };
        }

        // Global range messages (grouped)
        if ($this->flags & FILTER_FLAG_GLOBAL_RANGE) {
            return match (true) {
                $this->ipv4Only => "The {$field} must be a valid global IPv4 address.",
                $this->ipv6Only => "The {$field} must be a valid global IPv6 address.",
                default => "The {$field} must be a valid global IP address.",
            };
        }

        // Version-only or default
        return match (true) {
            $this->ipv4Only => "The {$field} must be a valid IPv4 address.",
            $this->ipv6Only => "The {$field} must be a valid IPv6 address.",
            default => "The {$field} must be a valid IP address.",
        };
    }

    /**
     * Validate IP address with specified flags
     * Complexity: O(1)
     */
    public function passes(mixed $value, string $field, array $data): bool
    {
        // Fast type check
        if (!is_string($value)) {
            return false;
        }

        // Fast length check (minimum valid IP is 7 chars: "0.0.0.0" or "::1")
        $length = strlen($value);
        if ($length < 3) {
            return false;
        }

        // Handle private-only validation separately
        if ($this->privateOnly) {
            return $this->isPrivateIp($value);
        }

        // Standard validation with flags
        return filter_var($value, FILTER_VALIDATE_IP, $this->flags) !== false;
    }

    /**
     * Build filter flags based on parsed options
     * Complexity: O(1)
     */
    protected function buildFlags(): void
    {
        // IPv4 only
        if ($this->ipv4Only && !$this->ipv6Only) {
            $this->flags |= FILTER_FLAG_IPV4;
        }

        // IPv6 only
        if ($this->ipv6Only && !$this->ipv4Only) {
            $this->flags |= FILTER_FLAG_IPV6;
        }

        // Public only = no private AND no reserved
        if ($this->publicOnly) {
            $this->flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }

        // Note: privateOnly is handled separately in passes() method
        // because PHP doesn't have a flag for "private only"
    }

    /**
     * Check if IP is private (no PHP flag for this)
     * Complexity: O(1)
     */
    protected function isPrivateIp(string $value): bool
    {
        // First, must be a valid IP
        $flags = 0;

        if ($this->ipv4Only) {
            $flags |= FILTER_FLAG_IPV4;
        }

        if ($this->ipv6Only) {
            $flags |= FILTER_FLAG_IPV6;
        }

        if (filter_var($value, FILTER_VALIDATE_IP, $flags) === false) {
            return false;
        }

        // Check if it's NOT public (i.e., it's private or reserved)
        // We validate that it fails when NO_PRIV_RANGE flag is set
        $publicFlags = $flags | FILTER_FLAG_NO_PRIV_RANGE;

        return filter_var($value, FILTER_VALIDATE_IP, $publicFlags) === false;
    }

    /**
     * Parse options string and set flags
     * Complexity: O(n) where n is number of options
     */
    protected function parseOptions(string $options): void
    {
        // Split by comma and process each option
        $parts = explode(',', strtolower($options));
        foreach ($parts as $option) {
            match (trim($option)) {
                'v4', '4', 'ipv4' => $this->ipv4Only = true,
                'v6', '6', 'ipv6' => $this->ipv6Only = true,
                'public' => $this->publicOnly = true,
                'private', 'priv' => $this->privateOnly = true,
                'no_res', 'no_reserved' => $this->flags |= FILTER_FLAG_NO_RES_RANGE,
                'no_priv', 'no_private' => $this->flags |= FILTER_FLAG_NO_PRIV_RANGE,
                'global' => $this->flags |= FILTER_FLAG_GLOBAL_RANGE,
                default => null,
            };
        }
    }

}
