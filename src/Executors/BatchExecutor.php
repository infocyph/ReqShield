<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

/**
 * BatchExecutor
 *
 * Executes expensive validation rules in batches to minimize database queries.
 * Groups rules by type and table, then executes them in a single query.
 *
 * OPTIMIZED:
 * - Reduced loop iterations
 * - Split complex methods
 * - Better memory efficiency
 * - Single-pass processing where possible
 */
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
     * @param  array  $batch  Array of ['rule' => Rule, 'value' => mixed, 'field' => string]
     * @param  array  $errors  Reference to errors array
     */
    public function executeBatch(array $batch, array &$errors): void
    {
        if (! $this->db || empty($batch)) {
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
     * Build query data for exists checks
     * OPTIMIZED: Single loop to build query and value tracking
     */
    protected function buildExistsQueryData(string $table, array $checks): array
    {
        $conditions = [];
        $params = [];
        $checkIndex = [];

        foreach ($checks as $idx => $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn();
            $value = $check['value'];

            // Build condition
            $conditions[] = "{$column} = ?";
            $params[] = $value;

            // Index check by column and value for fast lookup
            $key = $this->makeCheckKey($column, $value);
            $checkIndex[$key] = $check;
        }

        return [
            'query' => "SELECT * FROM {$table} WHERE ".implode(' OR ', $conditions),
            'params' => $params,
            'checkIndex' => $checkIndex,
        ];
    }

    /**
     * Build set of found keys for exists validation
     * OPTIMIZED: HashSet for O(1) lookups
     */
    protected function buildFoundKeysSet(array $results, array $checks): array
    {
        $foundKeys = [];

        // Get all columns we need to check
        $columnsToCheck = $this->extractColumnsFromChecks($checks);

        // Mark all found column:value combinations
        foreach ($results as $row) {
            foreach ($columnsToCheck as $column) {
                if (isset($row[$column])) {
                    $key = $this->makeCheckKey($column, $row[$column]);
                    $foundKeys[$key] = true;
                }
            }
        }

        return $foundKeys;
    }

    /**
     * Build query data for unique checks
     * OPTIMIZED: Single loop to build query and tracking data
     */
    protected function buildUniqueQueryData(string $table, array $checks): array
    {
        $conditions = [];
        $params = [];
        $checkMap = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $value = $check['value'];

            // Build condition
            $conditions[] = "{$column} = ?";
            $params[] = $value;

            // Track check for result processing
            $key = $this->makeCheckKey($column, $value);
            $checkMap[$key] = $check;
        }

        return [
            'query' => "SELECT * FROM {$table} WHERE ".implode(' OR ', $conditions),
            'params' => $params,
            'checkMap' => $checkMap,
        ];
    }

    /**
     * Build lookup map of found values
     * OPTIMIZED: Single pass through results
     */
    protected function buildValueLookup(array $results, array $checks): array
    {
        $lookup = [];

        // Get all columns we're checking
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
        return $column.':'.(string) $value;
    }

    /**
     * Process exists checks for a single table
     * OPTIMIZED: Split from batch method for clarity
     */
    protected function processExistsChecksForTable(string $table, array $checks, array &$errors): void
    {
        // Build query components in single pass
        $queryData = $this->buildExistsQueryData($table, $checks);

        // Execute and process results
        $results = $this->db->query($queryData['query'], $queryData['params']);

        // Build lookup map and validate
        $this->validateExistsResults($results, $checks, $errors);
    }

    /**
     * Process unique checks for a single table
     * OPTIMIZED: Split from batch method for clarity
     */
    protected function processUniqueChecksForTable(string $table, array $checks, array &$errors): void
    {
        // Build query components in single pass
        $queryData = $this->buildUniqueQueryData($table, $checks);

        // Execute and process results
        $results = $this->db->query($queryData['query'], $queryData['params']);

        // Process results efficiently
        $this->processUniqueResults($results, $checks, $errors);
    }

    /**
     * Process unique validation results
     * OPTIMIZED: Single pass through results, early termination per check
     */
    protected function processUniqueResults(array $results, array $checks, array &$errors): void
    {
        if (empty($results)) {
            return; // All values are unique (good!)
        }

        // Build fast lookup: column:value => row data
        $foundValues = $this->buildValueLookup($results, $checks);

        // Validate each check against found values
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $value = $check['value'];
            $key = $this->makeCheckKey($column, $value);

            if (! isset($foundValues[$key])) {
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

        return isset($row[$idColumn]) && $row[$idColumn] == $ignoreId;
    }

    /**
     * Validate exists results
     * OPTIMIZED: Build found set once, then validate all checks
     */
    protected function validateExistsResults(array $results, array $checks, array &$errors): void
    {
        // Build set of found values for O(1) lookup
        $foundKeys = $this->buildFoundKeysSet($results, $checks);

        // Validate each check - single pass
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn();
            $value = $check['value'];
            $key = $this->makeCheckKey($column, $value);

            if (! isset($foundKeys[$key])) {
                // Value doesn't exist in database - validation failed
                $errors[$check['field']][] = $rule->message($check['field']);
            }
        }
    }
}
