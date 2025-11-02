<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

class BatchExecutor
{
    protected ?DatabaseProvider $db;

    public function __construct(?DatabaseProvider $db = null)
    {
        $this->db = $db;
    }

    /**
     * Execute a batch of expensive rules.
     * OPTIMIZED: Single loop to categorize all rules
     *
     * @param array $batch Array of ['rule' => Rule, 'value' => mixed, 'field'
     *     => string]
     * @param array $errors Reference to errors array
     */
    public function executeBatch(array $batch, array &$errors): void
    {
        if (!$this->db || empty($batch)) {
            return;
        }

        // OPTIMIZATION: Single pass to categorize rules by type AND table
        $categorized = $this->categorizeRulesByTypeAndTable($batch);

        // Execute batched checks for each table
        foreach ($categorized['unique'] as $table => $checks) {
            $this->processUniqueChecksForTable($table, $checks, $errors);
        }

        foreach ($categorized['exists'] as $table => $checks) {
            $this->processExistsChecksForTable($table, $checks, $errors);
        }
    }

    /**
     * Set the database provider.
     */
    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }

    /**
     * Build lookup structure from database results
     * OPTIMIZED: Unified method for both exists and unique checks
     */
    protected function buildLookupFromResults(
        array $results,
        array $checks,
    ): array {
        $lookup = [];

        // Get all columns we need to check
        $columnsToCheck = $this->extractColumnsFromChecks($checks);

        // Build lookup for all found values
        foreach ($results as $row) {
            foreach ($columnsToCheck as $column) {
                if (isset($row[$column])) {
                    $key = $this->makeCheckKey($column, $row[$column]);
                    $lookup[$key] = $row;
                }
            }
        }

        return $lookup;
    }

    /**
     * Build query data for database checks
     * OPTIMIZED: Unified method to eliminate duplicate code between exists and
     * unique FIXED: Removed costly array_merge in loop
     */
    protected function buildQueryData(
        string $table,
        array $checks,
        bool $isUnique = false,
    ): array {
        // Group values by column for efficient IN clauses
        $grouped = [];
        $checkIndex = [];
        $idColumns = [];

        foreach ($checks as $idx => $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $value = $check['value'];

            $grouped[$column][] = $value;
            $checkIndex[$this->makeCheckKey($column, $value)] = $check;

            // For unique checks, track ID columns
            if ($isUnique) {
                $idColumns[$rule->getIdColumn() ?? 'id'] = true;
            }
        }

        // Build query with IN clauses
        // FIXED: Use array spread operator instead of array_merge in loop
        $conditions = [];
        $params = [];

        foreach ($grouped as $column => $values) {
            $escapedCol = $this->escapeIdentifier($column);
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $conditions[] = "{$escapedCol} IN ({$placeholders})";
            // FIXED: Use array spread instead of array_merge
            array_push($params, ...$values);
        }

        $escapedTable = $this->escapeIdentifier($table);

        // Select columns needed
        if ($isUnique) {
            $allCols = array_unique(
                array_merge(array_keys($grouped), array_keys($idColumns)),
            );
        } else {
            $allCols = array_keys($grouped);
        }

        $selectCols = implode(
            ', ',
            array_map([$this, 'escapeIdentifier'], $allCols),
        );

        return [
            'query' => "SELECT {$selectCols} FROM {$escapedTable} WHERE " . implode(
                ' OR ',
                $conditions,
            ),
            'params' => $params,
            'checkIndex' => $checkIndex,
        ];
    }

    /**
     * Categorize rules by type and table in a single pass
     * OPTIMIZED: One loop instead of multiple
     */
    protected function categorizeRulesByTypeAndTable(array $batch): array
    {
        $categorized = [
            'unique' => [],
            'exists' => [],
        ];

        foreach ($batch as $item) {
            $rule = $item['rule'];

            if ($rule instanceof Unique) {
                $table = $rule->getTable();
                $categorized['unique'][$table][] = $item;
            } elseif ($rule instanceof Exists) {
                $table = $rule->getTable();
                $categorized['exists'][$table][] = $item;
            }
        }

        return $categorized;
    }

    /**
     * Escape database identifier to prevent SQL injection.
     * Validates and wraps identifier in backticks.
     */
    protected function escapeIdentifier(string $identifier): string
    {
        $identifier = str_replace('`', '', $identifier);

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid identifier: {$identifier}",
            );
        }

        return "`{$identifier}`";
    }

    /**
     * Extract unique columns from checks
     * OPTIMIZED: Use array_unique to avoid duplicate column checks
     */
    protected function extractColumnsFromChecks(array $checks): array
    {
        $columns = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];

            if ($rule instanceof Unique) {
                $columns[] = $rule->getColumn() ?? $check['field'];
            } elseif ($rule instanceof Exists) {
                $columns[] = $rule->getColumn();
            }
        }

        return array_unique($columns);
    }

    /**
     * Create consistent key for column:value pairs
     * OPTIMIZED: Single point for key generation
     */
    protected function makeCheckKey(string $column, mixed $value): string
    {
        if (is_array($value)) {
            $value = 'array:' . json_encode($value);
        } elseif (is_object($value)) {
            $value = 'object:' . json_encode($value);
        } elseif (is_bool($value)) {
            $value = $value ? 'bool:true' : 'bool:false';
        } elseif (is_null($value)) {
            $value = 'null';
        } else {
            $value = (string)$value;
        }

        return $column . ':' . $value;
    }

    /**
     * Process exists checks for a single table
     * OPTIMIZED: Uses unified buildQueryData method
     */
    protected function processExistsChecksForTable(
        string $table,
        array $checks,
        array &$errors,
    ): void {
        // Build query components in single pass
        $queryData = $this->buildQueryData($table, $checks, false);

        // Execute and process results
        $results = $this->db->query($queryData['query'], $queryData['params']);

        // Build lookup map and validate
        $this->validateExistsResults($results, $checks, $errors);
    }

    /**
     * Process unique checks for a single table
     * OPTIMIZED: Uses unified buildQueryData method
     */
    protected function processUniqueChecksForTable(
        string $table,
        array $checks,
        array &$errors,
    ): void {
        // Build query components in single pass
        $queryData = $this->buildQueryData($table, $checks, true);

        // Execute and process results
        $results = $this->db->query($queryData['query'], $queryData['params']);

        // Process results efficiently
        $this->processUniqueResults($results, $checks, $errors);
    }

    /**
     * Process unique validation results
     * OPTIMIZED: Single pass through results, early termination per check
     */
    protected function processUniqueResults(
        array $results,
        array $checks,
        array &$errors,
    ): void {
        if (empty($results)) {
            return; // All values are unique (good!)
        }

        // Build fast lookup: column:value => row data
        $foundValues = $this->buildLookupFromResults($results, $checks);

        // Validate each check against found values
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $value = $check['value'];
            $key = $this->makeCheckKey($column, $value);

            if (!isset($foundValues[$key])) {
                continue; // Value is unique (not found in DB)
            }

            // Value exists - check if we should ignore it
            if ($this->shouldIgnoreUniqueMatch($foundValues[$key], $rule)) {
                continue;
            }

            // Add error - value is not unique
            $errors[$check['field']][] = $rule->message($check['field']);
        }
    }

    /**
     * Check if unique match should be ignored
     * OPTIMIZED: Extracted to separate method for clarity
     */
    protected function shouldIgnoreUniqueMatch(array $row, Unique $rule): bool
    {
        $ignoreId = $rule->getIgnoreId();

        if ($ignoreId === null) {
            return false;
        }

        $idColumn = $rule->getIdColumn() ?? 'id';

        return isset($row[$idColumn]) && (int)$row[$idColumn] === $ignoreId;
    }

    /**
     * Validate exists results
     * OPTIMIZED: Build found set once, then validate all checks
     */
    protected function validateExistsResults(
        array $results,
        array $checks,
        array &$errors,
    ): void {
        // Build set of found values for O(1) lookup
        $foundKeys = $this->buildLookupFromResults($results, $checks);

        // Validate each check - single pass
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn();
            $value = $check['value'];
            $key = $this->makeCheckKey($column, $value);

            if (!isset($foundKeys[$key])) {
                // Value doesn't exist in database - validation failed
                $errors[$check['field']][] = $rule->message($check['field']);
            }
        }
    }

}
