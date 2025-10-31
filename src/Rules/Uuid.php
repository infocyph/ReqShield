<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Uuid Rule - Cost: 15
 * Value must be a valid UUID
 *
 * Supports:
 * - Any version: uuid
 * - Specific version: uuid:4
 * - Multiple versions: uuid:345 (versions 3, 4, 5)
 * - Version range: uuid:3-5 (versions 3, 4, 5)
 * - Exclude version: uuid:!5 (all except version 5)
 *
 * @example
 * 'id' => 'uuid'       // Any version (1-9)
 * 'id' => 'uuid:4'     // Only version 4
 * 'id' => 'uuid:45'    // Version 4 or 5
 * 'id' => 'uuid:3-5'   // Version 3, 4, or 5
 * 'id' => 'uuid:!2'    // Any version except 2
 */
class Uuid extends BaseRule
{
    protected array $allowedVersions = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    protected bool $excludeMode = false;

    protected string $versionPattern;

    /**
     * @param string|int|null $version Version specification (e.g., "4", "345",
     *                                "3-5", "!2")
     */
    public function __construct(string|int|null $version = null)
    {
        $this->parseVersionSpec($version);
        $this->versionPattern = $this->buildVersionPattern();
    }

    public function cost(): int
    {
        return 15;
    }

    /**
     * Generate contextual error message
     * Complexity: O(1)
     */
    public function message(string $field): string
    {
        $count = count($this->allowedVersions);

        // All versions
        if ($count === 9) {
            return "The {$field} must be a valid UUID.";
        }

        // Single version
        if ($count === 1) {
            return "The {$field} must be a valid UUID version {$this->allowedVersions[0]}.";
        }

        // Exclude mode
        if ($this->excludeMode) {
            $excluded = array_diff(range(1, 9), $this->allowedVersions);
            $excludedList = implode(', ', $excluded);

            return "The {$field} must be a valid UUID (excluding version {$excludedList}).";
        }

        // Multiple versions
        $versionList = implode(', ', $this->allowedVersions);

        return "The {$field} must be a valid UUID (version {$versionList}).";
    }

    /**
     * Validate UUID with version checking
     * Complexity: O(1) - regex is pre-compiled
     */
    public function passes(mixed $value, string $field, array $data): bool
    {
        // Fast type check
        if (! is_string($value)) {
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

    /**
     * Build regex pattern for allowed versions
     * Pre-computed in constructor for performance
     * Complexity: O(1)
     */
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
        return '['.implode('', $this->allowedVersions).']';
    }

    /**
     * Parse exclude version specification (!2)
     * Complexity: O(1)
     */
    protected function parseExcludeVersion(string $version): void
    {
        $this->excludeMode = true;
        $excludedVersion = (int) substr($version, 1);

        // Validate excluded version is in range 1-9
        if ($excludedVersion >= 1 && $excludedVersion <= 9) {
            $this->allowedVersions = array_diff(range(1, 9), [$excludedVersion]);
        }
    }

    /**
     * Parse multiple version specification (4 or 345)
     * Complexity: O(n) where n is length of version string
     */
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

    /**
     * Parse range version specification (3-5)
     * Complexity: O(1)
     */
    protected function parseRangeVersion(string $version): void
    {
        $parts = explode('-', $version, 2);
        $start = max(1, min(9, (int) $parts[0]));
        $end = max(1, min(9, (int) $parts[1]));

        $this->allowedVersions = range(min($start, $end), max($start, $end));
    }

    /**
     * Parse version specification and return allowed versions array
     * Complexity: O(n) where n is length of version string
     */
    protected function parseVersionSpec(string|int|null $version): void
    {
        if ($version !== null && $version !== '') {
            $version = trim((string)$version);
            match (true) {
                $version[0] === '!' => $this->parseExcludeVersion($version),
                str_contains($version, '-') => $this->parseRangeVersion($version),
                default => $this->parseMultipleVersions($version),
            };
        }
    }
}
