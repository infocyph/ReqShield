<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

class BatchExecutor
{
    protected const BATCH_CHECK_CHUNK_SIZE = 500;

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
    public function executeBatch(array $batch, array &$errors, array &$failures = []): void
    {
        if (!$this->db || empty($batch)) {
            return;
        }

        $categorized = $this->categorizeRulesByTypeAndTable($batch);

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
     * Categorize rules by type and table in a single pass.
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
                $categorized['unique'][$rule->getTable()][] = $item;
                continue;
            }

            if ($rule instanceof Exists) {
                $categorized['exists'][$rule->getTable()][] = $item;
            }
        }

        return $categorized;
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    protected function checksByField(array $checks): array
    {
        $checksByField = [];

        foreach ($checks as $check) {
            $field = isset($check['field']) && is_string($check['field'])
                ? $check['field']
                : (string)($check['field'] ?? '');

            if ($field === '') {
                continue;
            }

            $checksByField[$field][] = $check;
        }

        return $checksByField;
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

    protected function processExistsChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
    ): void {
        $checksByField = $this->checksByField($checks);
        $recorded = [];

        foreach (array_chunk($checks, self::BATCH_CHECK_CHUNK_SIZE) as $chunk) {
            $payload = [];

            foreach ($chunk as $check) {
                /** @var Exists $rule */
                $rule = $check['rule'];

                $payload[] = [
                    'column' => $rule->getColumn(),
                    'value' => $check['value'],
                    'field' => $check['field'],
                ];
            }

            $failedFields = $this->db->batchExistsCheck($table, $payload);
            $this->recordBatchFailures(
                $failedFields,
                $checksByField,
                $errors,
                $failures,
                'exists',
                $recorded,
            );
        }
    }

    protected function processUniqueChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
    ): void {
        $checksByField = $this->checksByField($checks);
        $recorded = [];

        foreach (array_chunk($checks, self::BATCH_CHECK_CHUNK_SIZE) as $chunk) {
            $payload = [];

            foreach ($chunk as $check) {
                /** @var Unique $rule */
                $rule = $check['rule'];

                $payload[] = [
                    'column' => $rule->getColumn() ?? $check['field'],
                    'value' => $check['value'],
                    'field' => $check['field'],
                    'ignore_id' => $rule->getIgnoreId(),
                    'id_column' => $rule->getIdColumn() ?? 'id',
                    'with_trashed' => $rule->getWithTrashed(),
                    'soft_delete_column' => $rule->getSoftDeleteColumn(),
                ];
            }

            $failedFields = $this->db->batchUniqueCheck($table, $payload);
            $this->recordBatchFailures(
                $failedFields,
                $checksByField,
                $errors,
                $failures,
                'unique',
                $recorded,
            );
        }
    }

    /**
     * Record validation failures returned from provider batch methods.
     *
     * @param array<int,mixed> $failedFields
     * @param array<string,array<int,array<string,mixed>>> $checksByField
     * @param array<string,bool> $recorded
     */
    protected function recordBatchFailures(
        mixed $failedFields,
        array $checksByField,
        array &$errors,
        array &$failures,
        string $defaultRuleName,
        array &$recorded,
    ): void {
        if (!is_array($failedFields)) {
            throw new \RuntimeException('Database provider batch methods must return an array of failed field identifiers.');
        }

        foreach ($failedFields as $failedField) {
            $field = is_string($failedField)
                ? $failedField
                : (string)$failedField;

            if (!isset($checksByField[$field])) {
                continue;
            }

            foreach ($checksByField[$field] as $check) {
                $ruleName = isset($check['rule_name']) && is_string($check['rule_name'])
                    ? $check['rule_name']
                    : $defaultRuleName;
                $key = $field . '|' . $ruleName . '|' . $this->makeValueKey($check['value'] ?? null);

                if (isset($recorded[$key])) {
                    continue;
                }

                $recorded[$key] = true;

                $message = $this->resolveFailureMessage($check, $check['rule']);
                $errors[$field][] = $message;
                $failures[] = [
                    'field' => $field,
                    'rule' => $ruleName,
                    'message' => $message,
                    'value' => $check['value'],
                ];
            }
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
}
