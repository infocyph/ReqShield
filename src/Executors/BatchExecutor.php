<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;

/**
 * @phpstan-type BatchItem array{
 *   rule:Rule,
 *   value:mixed,
 *   field:string,
 *   rule_name?:string,
 *   field_label?:string,
 *   message?:string,
 *   message_resolver?:callable(): string
 * }
 * @phpstan-type Failure array{field:string,rule:string,message:string,value:mixed}
 * @phpstan-type GroupedChecks array<string, array<int, BatchItem>>
 * @phpstan-type CategorizedBatch array{unique: GroupedChecks, exists: GroupedChecks}
 */
class BatchExecutor
{
    protected const BATCH_CHECK_CHUNK_SIZE = 500;

    public function __construct(protected ?DatabaseProvider $db = null) {}

    /**
     * @param array<int, BatchItem> $batch
     * @param array<string, array<int, string>> $errors
     * @param array<int, Failure> $failures
     */
    public function executeBatch(array $batch, array &$errors, array &$failures = []): void
    {
        $db = $this->db;
        if ($db === null || empty($batch)) {
            return;
        }

        $categorized = $this->categorizeRulesByTypeAndTable($batch);

        foreach ($categorized['unique'] as $table => $checks) {
            $this->processUniqueChecksForTable(
                $table,
                $checks,
                $errors,
                $failures,
                $db,
            );
        }

        foreach ($categorized['exists'] as $table => $checks) {
            $this->processExistsChecksForTable(
                $table,
                $checks,
                $errors,
                $failures,
                $db,
            );
        }
    }

    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }

    /**
     * @param array<int, BatchItem> $batch
     * @return CategorizedBatch
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
     * @param array<int, BatchItem> $checks
     * @return array<string, array<int, BatchItem>>
     */
    protected function checksByField(array $checks): array
    {
        $checksByField = [];

        foreach ($checks as $check) {
            $field = $this->normalizeFieldIdentifier($check['field']);

            if ($field === '') {
                continue;
            }

            $checksByField[$field][] = $check;
        }

        return $checksByField;
    }

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
            $value = $this->normalizeFieldIdentifier($value);
        }

        return $value;
    }

    protected function normalizeFieldIdentifier(
        mixed $value,
        string $fallback = '',
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $fallback;
    }

    /**
     * @param array<int, BatchItem> $checks
     * @param array<string, array<int, string>> $errors
     * @param array<int, Failure> $failures
     * @param callable(BatchItem):(array<string, mixed>|null) $payloadBuilder
     * @param callable(DatabaseProvider, string, array<int, array<string, mixed>>):array<int, int|string> $batchRunner
     */
    protected function processChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
        DatabaseProvider $db,
        string $failureType,
        callable $payloadBuilder,
        callable $batchRunner,
    ): void {
        $checksByField = $this->checksByField($checks);
        $recorded = [];

        foreach (array_chunk($checks, self::BATCH_CHECK_CHUNK_SIZE) as $chunk) {
            $payload = [];

            foreach ($chunk as $check) {
                $payloadItem = $payloadBuilder($check);
                if ($payloadItem === null) {
                    continue;
                }

                $payload[] = $payloadItem;
            }

            $failedFields = $batchRunner($db, $table, $payload);
            $this->recordBatchFailures(
                $failedFields,
                $checksByField,
                $errors,
                $failures,
                $failureType,
                $recorded,
            );
        }
    }

    /**
     * @param array<int, BatchItem> $checks
     * @param array<string, array<int, string>> $errors
     * @param array<int, Failure> $failures
     */
    protected function processExistsChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
        DatabaseProvider $db,
    ): void {
        $this->processChecksForTable(
            $table,
            $checks,
            $errors,
            $failures,
            $db,
            'exists',
            static function (array $check): ?array {
                if (!$check['rule'] instanceof Exists) {
                    return null;
                }

                return [
                    'column' => $check['rule']->getColumn(),
                    'value' => $check['value'],
                    'field' => $check['field'],
                ];
            },
            static fn(DatabaseProvider $provider, string $tableName, array $payload): array
                => $provider->batchExistsCheck($tableName, $payload),
        );
    }

    /**
     * @param array<int, BatchItem> $checks
     * @param array<string, array<int, string>> $errors
     * @param array<int, Failure> $failures
     */
    protected function processUniqueChecksForTable(
        string $table,
        array $checks,
        array &$errors,
        array &$failures,
        DatabaseProvider $db,
    ): void {
        $this->processChecksForTable(
            $table,
            $checks,
            $errors,
            $failures,
            $db,
            'unique',
            static function (array $check): ?array {
                if (!$check['rule'] instanceof Unique) {
                    return null;
                }

                return [
                    'column' => $check['rule']->getColumn() ?? $check['field'],
                    'value' => $check['value'],
                    'field' => $check['field'],
                    'ignore_id' => $check['rule']->getIgnoreId(),
                    'id_column' => $check['rule']->getIdColumn() ?? 'id',
                    'with_trashed' => $check['rule']->getWithTrashed(),
                    'soft_delete_column' => $check['rule']->getSoftDeleteColumn(),
                ];
            },
            static fn(DatabaseProvider $provider, string $tableName, array $payload): array
                => $provider->batchUniqueCheck($tableName, $payload),
        );
    }

    /**
     * @param array<int, int|string> $failedFields
     * @param array<string, array<int, BatchItem>> $checksByField
     * @param array<string, array<int, string>> $errors
     * @param array<int, Failure> $failures
     * @param array<string, true> $recorded
     */
    protected function recordBatchFailures(
        array $failedFields,
        array $checksByField,
        array &$errors,
        array &$failures,
        string $defaultRuleName,
        array &$recorded,
    ): void {
        foreach ($failedFields as $failedField) {
            $field = $this->normalizeFieldIdentifier($failedField);

            if (!isset($checksByField[$field])) {
                continue;
            }

            foreach ($checksByField[$field] as $check) {
                $ruleName = $check['rule_name'] ?? $defaultRuleName;
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

    /** @param BatchItem $check */
    protected function resolveFailureMessage(array $check, Rule $rule): string
    {
        if (isset($check['message'])) {
            return $check['message'];
        }

        if (isset($check['message_resolver'])) {
            try {
                $resolved = ($check['message_resolver'])();

                if ($resolved !== '') {
                    return $resolved;
                }
            } catch (\Throwable) {
                // Fall back to rule default message.
            }
        }

        $label = $check['field_label'] ?? $this->normalizeFieldIdentifier($check['field'], 'field');

        return $rule->message($label);
    }
}
