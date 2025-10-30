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
    protected array $allowedVersions = [];

    protected bool $excludeMode = false;

    protected ?string $versionSpec = null;

    /**
     * @param  string|null  $version  Version specification (e.g., "4", "345", "3-5", "!2")
     */
    public function __construct(?string $version = null)
    {
        $this->versionSpec = $version;
        $this->parseVersionSpec($version);
    }

    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        if (empty($this->allowedVersions) || count($this->allowedVersions) === 9) {
            return "The {$field} must be a valid UUID.";
        }

        if ($this->excludeMode) {
            $excluded = array_diff(range(1, 9), $this->allowedVersions);

            return "The {$field} must be a valid UUID (excluding version ".implode(', ', $excluded).').';
        }

        if (count($this->allowedVersions) === 1) {
            return "The {$field} must be a valid UUID version {$this->allowedVersions[0]}.";
        }

        $versions = implode(', ', $this->allowedVersions);

        return "The {$field} must be a valid UUID (version {$versions}).";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // If no versions specified, allow any version (1-9)
        if (empty($this->allowedVersions)) {
            $this->allowedVersions = range(1, 9);
        }

        // Build version pattern based on allowed versions
        $versionPattern = $this->buildVersionPattern();

        // UUID regex with dynamic version and RFC 4122 compliant variant
        $pattern = sprintf(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-%s[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $versionPattern
        );

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Build the version pattern for regex based on allowed versions
     */
    protected function buildVersionPattern(): string
    {
        if (count($this->allowedVersions) === 1) {
            // Single version: just the digit
            return (string) $this->allowedVersions[0];
        }

        if (count($this->allowedVersions) === 9) {
            // All versions: use \d
            return '\d';
        }

        // Multiple versions: use character class [345]
        return '['.implode('', $this->allowedVersions).']';
    }

    /**
     * Parse version specification and populate allowedVersions
     */
    protected function parseVersionSpec(?string $version): void
    {
        if ($version === null || $version === '') {
            // No parameters = any version (1-9)
            $this->allowedVersions = range(1, 9);

            return;
        }

        // Exclude mode: !5 means "all except 5"
        if (str_starts_with($version, '!')) {
            $this->excludeMode = true;
            $excludedVersion = (int) substr($version, 1);
            if ($excludedVersion >= 1 && $excludedVersion <= 9) {
                $this->allowedVersions = array_diff(range(1, 9), [$excludedVersion]);
            } else {
                $this->allowedVersions = range(1, 9);
            }

            return;
        }

        // Range mode: 3-5 means "3, 4, 5"
        if (str_contains($version, '-')) {
            [$start, $end] = explode('-', $version, 2);
            $start = max(1, min(9, (int) $start));
            $end = max(1, min(9, (int) $end));
            $this->allowedVersions = range(min($start, $end), max($start, $end));

            return;
        }

        // Multiple versions: "345" means "3, 4, 5"
        if (strlen($version) > 1) {
            $versions = str_split($version);
            $this->allowedVersions = array_map('intval', array_filter($versions, function ($v) {
                return is_numeric($v) && $v >= '1' && $v <= '9';
            }));

            // If no valid versions found, allow all
            if (empty($this->allowedVersions)) {
                $this->allowedVersions = range(1, 9);
            }

            return;
        }

        // Single version: "4" means only version 4
        $singleVersion = (int) $version;
        if ($singleVersion >= 1 && $singleVersion <= 9) {
            $this->allowedVersions = [$singleVersion];
        } else {
            $this->allowedVersions = range(1, 9);
        }
    }
}
