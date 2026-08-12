<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Executors;

use Infocyph\ReqShield\Contracts\DatabaseBatchRule;
use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\DatabaseProviderRequiredException;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;

/**
 * @phpstan-type BatchItem array{
 *   rule:Rule,value:mixed,field:string,rule_name?:string,field_label?:string,
 *   message?:string,message_resolver?:callable():string,field_fail_fast?:bool
 * }
 * @phpstan-type Failure array{field:string,rule:string,message:string,value:mixed}
 * @phpstan-type Prepared array{id:int,item:BatchItem,rule:DatabaseBatchRule,payload:array<string,mixed>}
 */
final class BatchExecutor
{
    public function __construct(private ?DatabaseProvider $db = null) {}

    /**
     * @param array<int,BatchItem> $batch
     * @param array<string,array<int,string>> $errors
     * @param-out array<string,array<int,string>> $errors
     * @param array<int,Failure> $failures
     * @param-out array<int,Failure> $failures
     */
    public function executeBatch(
        array $batch,
        array &$errors,
        array &$failures = [],
        bool $stopOnFirstError = false,
    ): void {
        if ($batch === []) {
            return;
        }

        if ($this->db === null) {
            throw DatabaseProviderRequiredException::forRule($batch[0]['rule_name'] ?? 'database');
        }

        $prepared = $this->prepare($batch);
        $failed = $this->runGroups($prepared, $this->db);
        $failedFields = [];

        foreach ($prepared as $check) {
            if (!isset($failed[$check['id']])) {
                continue;
            }

            $item = $check['item'];
            $field = $item['field'];
            if (($item['field_fail_fast'] ?? false) && isset($failedFields[$field])) {
                continue;
            }

            $message = $this->resolveFailureMessage($item, $check['rule']);
            $errors[$field][] = $message;
            $failures[] = [
                'field' => $field,
                'rule' => $item['rule_name'] ?? $check['rule']->operation(),
                'message' => $message,
                'value' => $item['value'],
            ];
            $failedFields[$field] = true;

            if ($stopOnFirstError) {
                break;
            }
        }
    }

    public function hasProvider(): bool
    {
        return $this->db !== null;
    }

    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }

    /**
     * @param array<int,BatchItem> $batch
     * @return list<Prepared>
     */
    private function prepare(array $batch): array
    {
        $prepared = [];
        foreach ($batch as $id => $item) {
            $rule = $item['rule'];
            if (!$rule instanceof DatabaseBatchRule) {
                throw new DatabaseValidationException('Unsupported database batch rule: ' . $rule::class);
            }

            $prepared[] = [
                'id' => $id,
                'item' => $item,
                'rule' => $rule,
                'payload' => ['id' => $id] + $rule->databasePayload($item['value'], $item['field']),
            ];
        }

        return $prepared;
    }

    /** @param BatchItem $item */
    private function resolveFailureMessage(array $item, Rule $rule): string
    {
        if (isset($item['message'])) {
            return $item['message'];
        }

        if (isset($item['message_resolver'])) {
            try {
                $resolved = ($item['message_resolver'])();
                if ($resolved !== '') {
                    return $resolved;
                }
            } catch (\Throwable) {
            }
        }

        return $rule->message($item['field_label'] ?? $item['field']);
    }

    /**
     * @param list<Prepared> $prepared
     * @return array<int,true>
     */
    private function runGroups(array $prepared, DatabaseProvider $db): array
    {
        $groups = [];
        foreach ($prepared as $check) {
            $key = $check['rule']->operation() . "\0" . $check['rule']->table();
            $groups[$key][] = $check;
        }

        $failed = [];
        foreach ($groups as $checks) {
            $rule = $checks[0]['rule'];
            $payload = array_column($checks, 'payload');

            try {
                $returned = $rule->operation() === 'unique'
                    ? $db->batchUnique($rule->table(), $payload)
                    : $db->batchExists($rule->table(), $payload);
            } catch (\Throwable $exception) {
                throw new DatabaseValidationException(
                    "Database validation failed for table '{$rule->table()}'.",
                    previous: $exception,
                );
            }

            $known = array_fill_keys(array_column($checks, 'id'), true);
            foreach ($returned as $id) {
                if (!is_int($id) || !isset($known[$id])) {
                    throw new DatabaseValidationException('Database provider returned an unknown or malformed check ID.');
                }

                $failed[$id] = true;
            }
        }

        return $failed;
    }
}
