<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

class FieldAlias
{
    /**
     * @var array<string,string>
     */
    protected array $aliases = [];

    /**
     * @var array<int,array{pattern:string,alias:string}>
     */
    protected array $wildcardPatterns = [];

    /**
     * @param array<string,string> $aliases
     */
    public function __construct(array $aliases = [])
    {
        if ($aliases !== []) {
            $this->setBatch($aliases, true);
        }
    }

    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->aliases;
    }

    public function clear(): void
    {
        $this->aliases = [];
        $this->wildcardPatterns = [];
    }

    public function get(string $field): string
    {
        if (array_key_exists($field, $this->aliases)) {
            return $this->aliases[$field];
        }

        $normalizedField = WildcardPath::normalizeIndexedField($field);
        if ($normalizedField !== $field && array_key_exists($normalizedField, $this->aliases)) {
            return $this->aliases[$normalizedField];
        }

        foreach ($this->wildcardPatterns as $entry) {
            if (preg_match($entry['pattern'], $field) === 1) {
                return $entry['alias'];
            }
        }

        return $this->humanize($field);
    }

    /**
     * @param array<int,string> $fields
     *
     * @return array<string,string>
     */
    public function getMany(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $this->get($field);
        }

        return $result;
    }

    public function has(string $field): bool
    {
        return isset($this->aliases[$field]);
    }

    public function remove(string $field): void
    {
        unset($this->aliases[$field]);
        $this->rebuildWildcardPatterns();
    }

    /**
     * @param array<int,string> $fields
     */
    public function removeMany(array $fields): void
    {
        foreach ($fields as $field) {
            unset($this->aliases[$field]);
        }
        $this->rebuildWildcardPatterns();
    }

    /**
     * @param string|array<string,string> $field
     */
    public function set(string|array $field, ?string $alias = null): void
    {
        if (is_array($field)) {
            $this->aliases = array_merge($this->aliases, $field);
        } elseif ($alias !== null) {
            $this->aliases[$field] = $alias;
        }

        $this->rebuildWildcardPatterns();
    }

    /**
     * @param array<string,string> $aliases
     */
    public function setBatch(array $aliases, bool $replace = false): void
    {
        if ($replace) {
            $this->aliases = $aliases;
        } else {
            $this->aliases = array_merge($this->aliases, $aliases);
        }

        $this->rebuildWildcardPatterns();
    }

    protected function humanize(string $field): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', $field));
    }

    protected function rebuildWildcardPatterns(): void
    {
        $this->wildcardPatterns = [];

        foreach ($this->aliases as $field => $alias) {
            if (!str_contains($field, '*')) {
                continue;
            }

            $this->wildcardPatterns[] = [
                'pattern' => WildcardPath::toRegex($field),
                'alias' => $alias,
            ];
        }
    }
}
