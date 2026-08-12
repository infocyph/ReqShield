<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

class Ip extends BaseRule
{
    protected int $flags = 0;

    protected bool $ipv4Only = false;

    protected bool $ipv6Only = false;

    protected bool $privateOnly = false;

    protected bool $publicOnly = false;

    public function __construct(string ...$options)
    {
        $this->parseOptions($options);
        $this->assertCompatibleOptions();
        $this->buildFlags();
    }

    public function cost(): int
    {
        return 10;
    }

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

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        // Fast type check
        if (!is_string($value)) {
            return false;
        }

        // Fast length check for obviously invalid short input.
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

    protected function assertCompatibleOptions(): void
    {
        if ($this->ipv4Only && $this->ipv6Only) {
            throw new \InvalidArgumentException('IP options v4 and v6 are mutually exclusive.');
        }

        if ($this->privateOnly && $this->publicOnly) {
            throw new \InvalidArgumentException('IP options private and public are mutually exclusive.');
        }
    }

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

        // privateOnly is handled separately in passes() because PHP has no private-only flag.
    }

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

    /** @param array<int|string,string> $options */
    protected function parseOptions(array $options): void
    {
        foreach ($options as $optionGroup) {
            foreach (explode(',', strtolower($optionGroup)) as $option) {
                match (trim($option)) {
                    '' => null,
                    'v4', '4', 'ipv4' => $this->ipv4Only = true,
                    'v6', '6', 'ipv6' => $this->ipv6Only = true,
                    'public' => $this->publicOnly = true,
                    'private', 'priv' => $this->privateOnly = true,
                    'no_res', 'no_reserved' => $this->flags |= FILTER_FLAG_NO_RES_RANGE,
                    'no_priv', 'no_private' => $this->flags |= FILTER_FLAG_NO_PRIV_RANGE,
                    'global' => $this->flags |= FILTER_FLAG_GLOBAL_RANGE,
                    default => throw new \InvalidArgumentException("Unknown IP option: {$option}"),
                };
            }
        }
    }
}
