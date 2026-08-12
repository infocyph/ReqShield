<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Integration\Database;

use Infocyph\DBLayer\Connection\Connection;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

final class DBLayerDatabaseProvider implements DatabaseProvider
{
    private const int CHUNK_SIZE = 400;

    public int $operations = 0;

    public function __construct(private readonly Connection $connection) {}

    public function batchExists(string $table, array $checks): array
    {
        $failed = [];

        foreach ($this->groupChecks($checks, ['column']) as $group) {
            $found = $this->matchedRows($table, $group, false);

            foreach ($group as $check) {
                if (!isset($found[$this->valueKey($check['value'] ?? null)])) {
                    $failed[] = $this->identifier($check['id'] ?? null);
                }
            }
        }

        return $failed;
    }

    public function batchUnique(string $table, array $checks): array
    {
        $failed = [];

        foreach ($this->groupChecks(
            $checks,
            ['column', 'id_column', 'include_trashed', 'soft_delete_column'],
        ) as $group) {
            $rowsByValue = $this->matchedRows($table, $group, true);

            foreach ($group as $check) {
                $rows = $rowsByValue[$this->valueKey($check['value'] ?? null)] ?? [];
                foreach ($rows as $row) {
                    $idColumn = $this->stringValue($check['id_column'] ?? 'id');
                    if (($check['ignore'] ?? null) !== null
                        && array_key_exists($idColumn, $row)
                        && $row[$idColumn] === $check['ignore']) {
                        continue;
                    }

                    $failed[] = $this->identifier($check['id'] ?? null);

                    break;
                }
            }
        }

        return $failed;
    }

    /**
     * @param list<array<string,mixed>> $checks
     * @param list<string> $keys
     * @return list<list<array<string,mixed>>>
     */
    private function groupChecks(array $checks, array $keys): array
    {
        $groups = [];

        foreach ($checks as $check) {
            $parts = [];
            foreach ($keys as $key) {
                $parts[] = $this->valueKey($check[$key] ?? null);
            }

            $groups[implode('|', $parts)][] = $check;
        }

        return array_values($groups);
    }

    private function identifier(mixed $id): int|string
    {
        if (is_int($id) || is_string($id)) {
            return $id;
        }

        throw new \InvalidArgumentException('Database check identifiers must be integers or strings.');
    }

    /**
     * @param list<array<string,mixed>> $checks
     * @return array<string,mixed>
     */
    private function matchedRows(string $table, array $checks, bool $unique): array
    {
        $first = $checks[0];
        $column = $this->stringValue($first['column'] ?? null);
        $idColumn = $this->stringValue($first['id_column'] ?? 'id');
        $softDeleteColumn = isset($first['soft_delete_column']) && is_string($first['soft_delete_column'])
            ? $first['soft_delete_column']
            : null;
        $includeTrashed = ($first['include_trashed'] ?? false) === true;
        $values = [];
        $hasNull = false;

        foreach ($checks as $check) {
            $value = $check['value'] ?? null;
            if ($value === null) {
                $hasNull = true;

                continue;
            }

            $values[$this->valueKey($value)] = $value;
        }

        $rows = [];
        foreach (array_chunk(array_values($values), self::CHUNK_SIZE) as $chunk) {
            $select = $unique ? [$column, $idColumn] : [$column];
            if ($unique && $softDeleteColumn !== null) {
                $select[] = $softDeleteColumn;
            }

            $query = $this->connection->table($table)
                ->select($select)
                ->whereIn($column, $chunk);

            if ($unique && !$includeTrashed && $softDeleteColumn !== null) {
                $query->whereNull($softDeleteColumn);
            }

            ++$this->operations;
            foreach ($query->get() as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        if ($hasNull) {
            $select = $unique ? [$column, $idColumn] : [$column];
            if ($unique && $softDeleteColumn !== null) {
                $select[] = $softDeleteColumn;
            }

            $query = $this->connection->table($table)
                ->select($select)
                ->whereNull($column);

            if ($unique && !$includeTrashed && $softDeleteColumn !== null) {
                $query->whereNull($softDeleteColumn);
            }

            ++$this->operations;
            foreach ($query->get() as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->valueKey($row[$column] ?? null);
            if ($unique) {
                $indexed[$key][] = $row;
            } else {
                $indexed[$key] = true;
            }
        }

        return $indexed;
    }

    private function stringValue(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Database table and column names must be non-empty strings.');
        }

        return $value;
    }

    private function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            $value = (int) $value;
        }

        if (is_scalar($value)) {
            return 'scalar:' . (string) $value;
        }

        return get_debug_type($value) . ':' . serialize($value);
    }
}
