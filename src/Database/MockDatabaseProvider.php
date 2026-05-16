<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Database;

use Infocyph\ReqShield\Contracts\DatabaseProvider;

class MockDatabaseProvider implements DatabaseProvider
{
    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $data = [];

    /** @param array<int, array<string, mixed>> $rows */
    public function addData(string $table, array $rows): void
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException(
                'Rows must be a non-empty array',
            );
        }
        $this->data[$table] = $rows;
    }

    /**
     * @param array<int|string, mixed> $checks
     * @return array<int, int|string>
     */
    public function batchExistsCheck(string $table, array $checks): array
    {
        if (!MockDatabaseBatchHelper::looksLikeStructuredBatch($checks)) {
            return $this->legacyBatchExistsCheck($table, $checks);
        }

        $missing = [];
        $rows = $this->data[$table] ?? [];

        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }

            $column = MockDatabaseBatchHelper::stringIdentifier($check['column'] ?? null);
            $value = $check['value'] ?? null;
            $identifier = MockDatabaseBatchHelper::stringIdentifier($check['field'] ?? $value);

            if ($column === '') {
                continue;
            }

            $found = false;
            foreach ($rows as $row) {
                if (array_key_exists($column, $row) && $row[$column] === $value) {
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                $missing[] = $identifier;
            }
        }

        return $missing;
    }

    /**
     * @param array<int|string, mixed> $checks
     * @return array<int, int|string>
     */
    public function batchUniqueCheck(string $table, array $checks): array
    {
        if (!MockDatabaseBatchHelper::looksLikeStructuredBatch($checks)) {
            return $this->legacyBatchUniqueCheck($table, $checks);
        }

        $rows = $this->data[$table] ?? [];
        $nonUnique = [];

        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }

            $normalized = MockDatabaseBatchHelper::normalizeUniqueCheck($check);
            if ($normalized['column'] === '') {
                continue;
            }

            if (MockDatabaseBatchHelper::hasUniqueConflict($rows, $normalized)) {
                $nonUnique[] = $normalized['identifier'];
            }
        }

        return $nonUnique;
    }

    /** @param array<string, mixed> $columns */
    public function compositeUnique(
        string $table,
        array $columns,
        ?int $ignoreId = null,
    ): bool {
        if (!isset($this->data[$table])) {
            return true; // No data, so it's unique
        }

        foreach ($this->data[$table] as $row) {
            // Check if we should ignore this row
            if ($ignoreId && isset($row['id']) && $row['id'] === $ignoreId) {
                continue;
            }
            $allMatch = array_all(
                $columns,
                fn(
                    $value,
                    $column,
                )
                  => !(!isset($row[$column]) || $row[$column] !== $value),
            );

            if ($allMatch) {
                return false; // Found a matching row, not unique
            }
        }

        return true; // No matching row found, it's unique
    }

    public function exists(
        string $table,
        string $column,
        mixed $value,
        ?int $ignoreId = null,
    ): bool {
        if (!isset($this->data[$table])) {
            return false;
        }

        foreach ($this->data[$table] as $row) {
            if (isset($row[$column]) && $row[$column] === $value) {
                // Check if we should ignore this row
                if ($ignoreId && isset($row['id']) && $row['id'] === $ignoreId) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $query, array $params = []): array
    {
        // This is a simplified mock - in real implementation, use PDO or your DB layer

        // Extract table name from query
        preg_match('/FROM\s+(\w+)/i', $query, $matches);
        $table = $matches[1] ?? null;

        if (!$table || !isset($this->data[$table])) {
            return [];
        }

        // Simple filtering (very basic mock)
        $results = [];
        foreach ($this->data[$table] as $row) {
            // Check if any column matches any param
            foreach ($row as $value) {
                if (in_array($value, $params, true)) {
                    $results[] = $row;

                    break;
                }
            }
        }

        return $results;
    }

    /**
     * @param array<int|string, mixed> $checks
     * @return array<int, int|string>
     */
    protected function legacyBatchExistsCheck(string $table, array $checks): array
    {
        $missing = [];

        if (!isset($this->data[$table])) {
            foreach ($checks as $column => $value) {
                $missing[] = MockDatabaseBatchHelper::normalizeFailedIdentifier($value, $column);
            }

            return $missing;
        }

        foreach ($checks as $column => $value) {
            $found = array_any(
                $this->data[$table],
                fn(array $row): bool => isset($row[$column]) && $row[$column] === $value,
            );
            if (!$found) {
                $missing[] = MockDatabaseBatchHelper::normalizeFailedIdentifier($value, $column);
            }
        }

        return $missing;
    }

    /**
     * @param array<int|string, mixed> $checks
     * @return array<int, int|string>
     */
    protected function legacyBatchUniqueCheck(string $table, array $checks): array
    {
        $nonUnique = [];

        if (!isset($this->data[$table])) {
            return $nonUnique;
        }

        foreach ($checks as $column => $value) {
            foreach ($this->data[$table] as $row) {
                if (isset($row[$column]) && $row[$column] === $value) {
                    $nonUnique[] = MockDatabaseBatchHelper::normalizeFailedIdentifier($value, $column);

                    break;
                }
            }
        }

        return $nonUnique;
    }
}
