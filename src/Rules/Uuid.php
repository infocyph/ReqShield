<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

class Uuid extends BaseRule
{
    /** @var array<int, int> */
    protected array $allowedVersions = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    protected bool $excludeMode = false;

    protected string $versionPattern;

    public function __construct(string|int|null $version = null)
    {
        $this->parseVersionSpec($version);
        $this->versionPattern = $this->buildVersionPattern();
    }

    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        $count = count($this->allowedVersions);

        // All versions
        if ($count === 9) {
            return "The {$field} must be a valid UUID.";
        }

        // Single version
        if ($count === 1) {
            return "The {$field} must be a valid UUID version "
                . $this->stringifyValue($this->allowedVersions[0]) . '.';
        }

        // Exclude mode
        if ($this->excludeMode) {
            $excluded = array_values(array_diff(range(1, 9), $this->allowedVersions));
            $excludedList = implode(', ', array_map(
                static fn(int $version): string => (string) $version,
                $excluded,
            ));

            return "The {$field} must be a valid UUID (excluding version {$excludedList}).";
        }

        // Multiple versions
        $versionList = implode(', ', array_map(
            static fn(int $version): string => (string) $version,
            $this->allowedVersions,
        ));

        return "The {$field} must be a valid UUID (version {$versionList}).";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        // Fast type check
        if (!is_string($value)) {
            return false;
        }

        // Fast length check (UUID must be 36 characters)
        if (strlen($value) !== 36) {
            return false;
        }

        // Pattern: xxxxxxxx-xxxx-V[0-9a-f]{3}-[89ab][0-9a-f]{3}-xxxxxxxxxxxx
        return preg_match(
            "/^[0-9a-f]{8}-[0-9a-f]{4}-$this->versionPattern[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
            $value,
        ) === 1;
    }

    protected function buildVersionPattern(): string
    {
        $count = count($this->allowedVersions);

        // Single version: use exact digit
        if ($count === 1) {
            return (string) $this->allowedVersions[0];
        }

        // All versions: use \d
        if ($count === 9) {
            return '\d';
        }

        // Multiple versions: use character class [345]
        return '[' . implode('', array_map(
            static fn(int $version): string => (string) $version,
            $this->allowedVersions,
        )) . ']';
    }

    protected function parseExcludeVersion(string $version): void
    {
        $this->excludeMode = true;
        $excludedVersion = (int) substr($version, 1);

        // Validate excluded version is in range 1-9
        if ($excludedVersion >= 1 && $excludedVersion <= 9) {
            $this->allowedVersions = array_values(array_diff(
                range(1, 9),
                [$excludedVersion],
            ));
        }
    }

    protected function parseMultipleVersions(string $version): void
    {
        $length = strlen($version);

        if ($length !== 1) {
            $versions = [];
            for ($i = 0; $i < $length; $i++) {
                $digit = (int) $version[$i];
                if ($digit >= 1 && $digit <= 9) {
                    $versions[] = $digit;
                }
            }
            $this->allowedVersions = $versions;

            return;
        }
        $singleVersion = (int) $version;
        if ($singleVersion >= 1 && $singleVersion <= 9) {
            $this->allowedVersions = [$singleVersion];
        }
    }

    protected function parseRangeVersion(string $version): void
    {
        $parts = explode('-', $version, 2);
        $start = max(1, min(9, (int) $parts[0]));
        $end = max(1, min(9, (int) $parts[1]));

        $this->allowedVersions = range(min($start, $end), max($start, $end));
    }

    protected function parseVersionSpec(string|int|null $version): void
    {
        if ($version !== null && $version !== '') {
            $version = trim((string) $version);
            match (true) {
                $version[0] === '!' => $this->parseExcludeVersion($version),
                str_contains($version, '-') => $this->parseRangeVersion(
                    $version,
                ),
                default => $this->parseMultipleVersions($version),
            };
        }
    }
}
