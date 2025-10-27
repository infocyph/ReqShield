<?php

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

/**
 * BatchExecutor
 *
 * Executes expensive validation rules in batches to minimize database queries.
 * Groups rules by type and table, then executes them in a single query.
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
     *
     * @param array $batch Array of ['rule' => Rule, 'value' => mixed, 'field' => string]
     * @param array $errors Reference to errors array
     */
    public function executeBatch(array $batch, array &$errors): void
    {
        if (!$this->db) {
            return; // No database provider, skip
        }

        // Group by rule type
        $uniqueChecks = [];
        $existsChecks = [];

        foreach ($batch as $item) {
            $rule = $item['rule'];

            if ($rule instanceof Unique) {
                $uniqueChecks[] = $item;
            } elseif ($rule instanceof Exists) {
                $existsChecks[] = $item;
            }
        }

        // Execute batched checks
        if (!empty($uniqueChecks)) {
            $this->batchUniqueChecks($uniqueChecks, $errors);
        }

        if (!empty($existsChecks)) {
            $this->batchExistsChecks($existsChecks, $errors);
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
     * Batch execute exists validation rules.
     */
    protected function batchExistsChecks(array $checks, array &$errors): void
    {
        // Group by table
        $byTable = [];
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $table = $rule->getTable();
            $byTable[$table][] = $check;
        }

        foreach ($byTable as $table => $tableChecks) {
            $this->executeBatchedExistsQuery($table, $tableChecks, $errors);
        }
    }

    /**
     * Batch execute unique validation rules.
     */
    protected function batchUniqueChecks(array $checks, array &$errors): void
    {
        // Group by table
        $byTable = [];
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $table = $rule->getTable();
            $byTable[$table][] = $check;
        }

        foreach ($byTable as $table => $tableChecks) {
            $this->executeBatchedUniqueQuery($table, $tableChecks, $errors);
        }
    }

    /**
     * Execute a single batched exists query for a table.
     */
    protected function executeBatchedExistsQuery(string $table, array $checks, array &$errors): void
    {
        $conditions = [];
        $params = [];
        $valueMap = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn();
            $value = $check['value'];

            $conditions[] = "{$column} = ?";
            $params[] = $value;

            // Track which values we're checking
            $key = $column . ':' . $value;
            $valueMap[$key] = $check;
        }

        // Build query
        $whereClause = implode(' OR ', $conditions);
        $query = "SELECT * FROM {$table} WHERE {$whereClause}";

        // Execute query
        $results = $this->db->query($query, $params);

        // Build set of found values
        $found = [];
        foreach ($results as $row) {
            foreach ($checks as $check) {
                $column = $check['rule']->getColumn();
                if (isset($row[$column])) {
                    $key = $column . ':' . $row[$column];
                    $found[$key] = true;
                }
            }
        }

        // Check which values were NOT found
        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn();
            $value = $check['value'];
            $key = $column . ':' . $value;

            if (!isset($found[$key])) {
                // Value doesn't exist
                $errors[$check['field']][] = $rule->message($check['field']);
            }
        }
    }

    /**
     * Execute a single batched unique query for a table.
     */
    protected function executeBatchedUniqueQuery(string $table, array $checks, array &$errors): void
    {
        $conditions = [];
        $params = [];
        $checkMap = [];

        foreach ($checks as $check) {
            $rule = $check['rule'];
            $column = $rule->getColumn() ?? $check['field'];
            $value = $check['value'];

            $conditions[] = "{$column} = ?";
            $params[] = $value;

            // Map to track which check corresponds to which result
            $checkMap[$column . ':' . $value] = $check;
        }

        // Build query
        $whereClause = implode(' OR ', $conditions);
        $query = "SELECT * FROM {$table} WHERE {$whereClause}";

        // Execute query
        $results = $this->db->query($query, $params);

        // Process results
        foreach ($results as $row) {
            foreach ($checks as $check) {
                $rule = $check['rule'];
                $column = $rule->getColumn() ?? $check['field'];
                $value = $check['value'];

                if (isset($row[$column]) && $row[$column] == $value) {
                    // Check if we should ignore this ID
                    $ignoreId = $rule->getIgnoreId();
                    $idColumn = $rule->getIdColumn() ?? 'id';

                    if ($ignoreId && isset($row[$idColumn]) && $row[$idColumn] == $ignoreId) {
                        continue; // Skip this one
                    }

                    // Value is not unique
                    $errors[$check['field']][] = $rule->message($check['field']);
                    break;
                }
            }
        }
    }
}
