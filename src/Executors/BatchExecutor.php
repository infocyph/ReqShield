<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\NativeBatchDatabaseProvider;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

class BatchExecutor
{
    protected const IN_CLAUSE_CHUNK_SIZE = 500;

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
    public function executeBatch(array $batch, array &$errors, array &$failures = []): void
    {
        if (!$this->db || empty($batch)) {
            return;
        }

        // OPTIMIZATION: Single pass to categorize rules by type AND table
        $categorized = $this->categorizeRulesByTypeAndTable($batch);

        // Execute batched checks for each table
        foreach ($categorized['unique'] as $table => $checks) {
            $this->processUniqueChecksForTable(
                $table,
                $checks,
                $errors,
                $failures,
            );
        }

        foreach ($categorized['exists'] as $table => $checks) {
            $this->processExistsChecksForTable(
                $table,
                $checks,
                $errors,
                $failures,
            );
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
     * Build query groups for database checks.
     *
     * Queries are split by column and chunked by value count so the database
     * can avoid broad OR scans.
     */
    protected function buildQueryGroups(
        string $table,
        array $checks,
        bool $isUnique = false,
    ): array {
        $grouped = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $groupKey = $column;

            if ($isUnique) {
                $idColumn = $rule->getIdColumn() ?? 'id';
                $groupKey .= '|trashed:' . (($rule->getWithTrashed() ?? false) ? '1' : '0');
                $groupKey .= '|soft:' . ($rule->getSoftDeleteColumn() ?? 'deleted_at');
                $groupKey .= '|id:' . $idColumn;
            }

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'column' => $column,
                    'values' => [],
                    'withTrashed' => $isUnique ? (bool)($rule->getWithTrashed() ?? false) : true,
                    'softDeleteColumn' => $isUnique ? (string)($rule->getSoftDeleteColumn() ?? 'deleted_at') : '',
                    'idColumns' => [],
                ];
            }

            $grouped[$groupKey]['values'][] = $check['value'];

            if ($isUnique) {
                $grouped[$groupKey]['idColumns'][$rule->getIdColumn() ?? 'id'] = true;
            }
        }

        $queries = [];

        foreach ($grouped as $group) {
            $values = $this->dedupeValues($group['values']);
            $idColumns = array_keys($group['idColumns']);

            foreach (array_chunk($values, self::IN_CLAUSE_CHUNK_SIZE) as $chunk) {
                $queries[] = $this->buildSingleColumnQuery(
                    $table,
                    $group['column'],
                    $chunk,
                    $isUnique ? $idColumns : [],
                    $isUnique ? $group['softDeleteColumn'] : null,
                    $isUnique ? $group['withTrashed'] : true,
                );
            }
        }

        return $queries;
    }

    /**
     * Build a single-column query with optional ID columns in select list.
     */
    protected function buildSingleColumnQuery(
        string $table,
        string $column,
        array $values,
        array $idColumns = [],
        ?string $softDeleteColumn = null,
        bool $withTrashed = true,
    ): array {
        $escapedTable = $this->escapeIdentifier($table);
        $escapedColumn = $this->escapeIdentifier($column);
        $selectColumns = array_values(array_unique(array_merge([$column], $idColumns)));
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $selectCols = implode(
            ', ',
            array_map([$this, 'escapeIdentifier'], $selectColumns),
        );

        $query = "SELECT {$selectCols} FROM {$escapedTable} WHERE {$escapedColumn} IN ({$placeholders})";

        if (!$withTrashed && is_string($softDeleteColumn) && $softDeleteColumn !== '') {
            $escapedSoftDelete = $this->escapeIdentifier($softDeleteColumn);
            $query .= " AND {$escapedSoftDelete} IS NULL";
        }

        return ['query' => $query, 'params' => $values];
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
     * Remove duplicate values while preserving first-seen order.
     */
    protected function dedupeValues(array $values): array
    {
        $unique = [];
        $seen = [];

        foreach ($values as $value) {
            $key = $this->makeValueKey($value);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $value;
        }

        return $unique;
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
     * Execute all query groups for a table and collect result rows.
     */
    protected function executeQueryGroups(
        string $table,
        array $checks,
        bool $isUnique,
    ): array {
        $results = [];

        foreach ($this->buildQueryGroups($table, $checks, $isUnique) as $queryData) {
            $rows = $this->db->query($queryData['query'], $queryData['params']);

            foreach ($rows as $row) {
                $results[] = $row;
            }
        }

        return $results;
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
        return $column . ':' . $this->makeValueKey($value);
    }

    /**
     * Create a stable key for value de-duplication.
     */
    protected function makeValueKey(mixed $value): string
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

        return $value;
    }

    /**
     * Process exists checks for a single table.
     */
    protected function processExistsChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
    ): void {
        if (
            $this->db instanceof NativeBatchDatabaseProvider
            && $this->processExistsChecksWithNativeBatch($table, $checks, $errors, $failures)
        ) {
            return;
        }

        $results = $this->executeQueryGroups($table, $checks, false);
        $this->validateExistsResults($results, $checks, $errors, $failures);
    }

    protected function processExistsChecksWithNativeBatch(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
    ): bool {
        $payload = [];
        $checksByField = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn();
            $field = $check['field'];

            $payload[] = [
                'column' => $column,
                'value' => $check['value'],
                'field' => $field,
            ];
            $checksByField[$field] = $check;
        }

        try {
            $failedFields = $this->db->batchExistsCheck($table, $payload);
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($failedFields)) {
            return false;
        }

        foreach ($failedFields as $failedField) {
            $field = is_string($failedField) ? $failedField : (string)$failedField;
            if (!isset($checksByField[$field])) {
                continue;
            }

            $check = $checksByField[$field];
            $message = $this->resolveFailureMessage($check, $check['rule']);
            $errors[$field][] = $message;
            $failures[] = [
                'field' => $field,
                'rule' => $check['rule_name'] ?? 'exists',
                'message' => $message,
                'value' => $check['value'],
            ];
        }

        return true;
    }

    /**
     * Process unique checks for a single table.
     */
    protected function processUniqueChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
    ): void {
        if (
            $this->db instanceof NativeBatchDatabaseProvider
            && $this->processUniqueChecksWithNativeBatch($table, $checks, $errors, $failures)
        ) {
            return;
        }

        $results = $this->executeQueryGroups($table, $checks, true);
        $this->processUniqueResults($results, $checks, $errors, $failures);
    }

    protected function processUniqueChecksWithNativeBatch(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
    ): bool {
        $payload = [];
        $checksByField = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $field = $check['field'];

            $payload[] = [
                'column' => $column,
                'value' => $check['value'],
                'field' => $field,
                'ignore_id' => $rule->getIgnoreId(),
                'id_column' => $rule->getIdColumn() ?? 'id',
                'with_trashed' => (bool)($rule->getWithTrashed() ?? false),
                'soft_delete_column' => $rule->getSoftDeleteColumn() ?? 'deleted_at',
            ];
            $checksByField[$field] = $check;
        }

        try {
            $failedFields = $this->db->batchUniqueCheck($table, $payload);
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($failedFields)) {
            return false;
        }

        foreach ($failedFields as $failedField) {
            $field = is_string($failedField) ? $failedField : (string)$failedField;
            if (!isset($checksByField[$field])) {
                continue;
            }

            $check = $checksByField[$field];
            $message = $this->resolveFailureMessage($check, $check['rule']);
            $errors[$field][] = $message;
            $failures[] = [
                'field' => $field,
                'rule' => $check['rule_name'] ?? 'unique',
                'message' => $message,
                'value' => $check['value'],
            ];
        }

        return true;
    }

    /**
     * Process unique validation results
     * OPTIMIZED: Single pass through results, early termination per check
     */
    protected function processUniqueResults(
        array $results,
        array $checks,
        array &$errors,
        array &$failures,
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
            $message = $this->resolveFailureMessage($check, $rule);
            $errors[$check['field']][] = $message;
            $failures[] = [
                'field' => $check['field'],
                'rule' => $check['rule_name'] ?? 'unique',
                'message' => $message,
                'value' => $value,
            ];
        }
    }

    /**
     * Resolve failure message using pre-rendered value when available.
     */
    protected function resolveFailureMessage(array $check, object $rule): string
    {
        if (isset($check['message']) && is_string($check['message'])) {
            return $check['message'];
        }

        if (isset($check['message_resolver']) && is_callable($check['message_resolver'])) {
            try {
                $resolved = ($check['message_resolver'])();

                if (is_string($resolved) && $resolved !== '') {
                    return $resolved;
                }
            } catch (\Throwable) {
                // Fall back to rule default message.
            }
        }

        $label = isset($check['field_label']) && is_string($check['field_label'])
            ? $check['field_label']
            : $check['field'];

        return $rule->message($label);
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
        array &$failures,
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
                $message = $this->resolveFailureMessage($check, $rule);
                $errors[$check['field']][] = $message;
                $failures[] = [
                    'field' => $check['field'],
                    'rule' => $check['rule_name'] ?? 'exists',
                    'message' => $message,
                    'value' => $value,
                ];
            }
        }
    }

}
