<?php

namespace Infocyph\ReqShield\Database;

use Infocyph\ReqShield\Contracts\NativeBatchDatabaseProvider;

/**
 * MockDatabaseProvider
 *
 * ⚠️ WARNING: FOR TESTING AND EXAMPLES ONLY ⚠️
 *
 * This is a simple in-memory database provider intended ONLY for:
 * - Unit testing
 * - Documentation examples
 * - Quick prototyping
 *
 * DO NOT USE IN PRODUCTION!
 *
 * For production use, implement DatabaseProvider with:
 * - PDO with prepared statements
 * - Your ORM (Eloquent, Doctrine, etc.)
 * - Proper query builder with parameter binding
 *
 * This mock implementation does NOT provide real security or performance.
 */
class MockDatabaseProvider implements NativeBatchDatabaseProvider
{
    /**
     * Mock database data.
     */
    protected array $data = [];

    /**
     * Add mock data.
     */
    public function addData(string $table, array $rows): void
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException(
                "Rows must be a non-empty array",
            );
        }
        $this->data[$table] = $rows;
    }

    /**
     * Batch check if values exist.
     */
    public function batchExistsCheck(string $table, array $checks): array
    {
        if (!$this->looksLikeStructuredBatch($checks)) {
            return $this->legacyBatchExistsCheck($table, $checks);
        }

        $missing = [];
        $rows = $this->data[$table] ?? [];

        foreach ($checks as $check) {
            $column = (string)($check['column'] ?? '');
            $value = $check['value'] ?? null;
            $identifier = (string)($check['field'] ?? $value);

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
     * Batch check if values are unique.
     */
    public function batchUniqueCheck(string $table, array $checks): array
    {
        if (!$this->looksLikeStructuredBatch($checks)) {
            return $this->legacyBatchUniqueCheck($table, $checks);
        }

        $nonUnique = [];
        $rows = $this->data[$table] ?? [];

        foreach ($checks as $check) {
            $column = (string)($check['column'] ?? '');
            $value = $check['value'] ?? null;
            $identifier = (string)($check['field'] ?? $value);
            $ignoreId = isset($check['ignore_id']) && is_int($check['ignore_id'])
                ? $check['ignore_id']
                : null;
            $idColumn = isset($check['id_column']) && is_string($check['id_column'])
                ? $check['id_column']
                : 'id';
            $withTrashed = isset($check['with_trashed']) && is_bool($check['with_trashed'])
                ? $check['with_trashed']
                : true;
            $softDeleteColumn = isset($check['soft_delete_column']) && is_string($check['soft_delete_column'])
                ? $check['soft_delete_column']
                : 'deleted_at';

            if ($column === '') {
                continue;
            }

            foreach ($rows as $row) {
                if (!$withTrashed && isset($row[$softDeleteColumn]) && $row[$softDeleteColumn] !== null) {
                    continue;
                }

                if (!array_key_exists($column, $row) || $row[$column] !== $value) {
                    continue;
                }

                if ($ignoreId !== null && isset($row[$idColumn]) && (int)$row[$idColumn] === $ignoreId) {
                    continue;
                }

                $nonUnique[] = $identifier;
                break;
            }
        }

        return $nonUnique;
    }

    /**
     * Check if a composite key is unique.
     */
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
                fn (
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

    /**
     * Check if a value exists in a table.
     *
     * @param mixed $value
     */
    public function exists(
        string $table,
        string $column,
        $value,
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
     * Execute a database query.
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

    protected function legacyBatchExistsCheck(string $table, array $checks): array
    {
        $missing = [];

        if (!isset($this->data[$table])) {
            return array_values($checks);
        }

        foreach ($checks as $column => $value) {
            $found = false;
            foreach ($this->data[$table] as $row) {
                if (isset($row[$column]) && $row[$column] === $value) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $value;
            }
        }

        return $missing;
    }

    protected function legacyBatchUniqueCheck(string $table, array $checks): array
    {
        $nonUnique = [];

        if (!isset($this->data[$table])) {
            return $nonUnique;
        }

        foreach ($checks as $column => $value) {
            foreach ($this->data[$table] as $row) {
                if (isset($row[$column]) && $row[$column] === $value) {
                    $nonUnique[] = $value;
                    break;
                }
            }
        }

        return $nonUnique;
    }

    protected function looksLikeStructuredBatch(array $checks): bool
    {
        if ($checks === []) {
            return true;
        }

        $first = array_values($checks)[0] ?? null;

        return is_array($first) && array_key_exists('column', $first);
    }

}
