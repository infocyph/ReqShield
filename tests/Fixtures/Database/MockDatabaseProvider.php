<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures\Database;

use Infocyph\ReqShield\Contracts\DatabaseProvider;

final class MockDatabaseProvider implements DatabaseProvider
{
    /** @var array<string,list<array<string,mixed>>> */
    private array $data = [];

    /** @param list<array<string,mixed>> $rows */
    public function addData(string $table, array $rows): void
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Rows must be a non-empty array.');
        }

        $this->data[$table] = $rows;
    }

    public function batchExists(string $table, array $checks): array
    {
        $failed = [];
        foreach ($checks as $check) {
            $found = false;
            foreach ($this->data[$table] ?? [] as $row) {
                $column = $check['column'] ?? null;
                if (is_string($column) && array_key_exists($column, $row) && $row[$column] === ($check['value'] ?? null)) {
                    $found = true;
                    break;
                }
            }

            if (!$found && isset($check['id']) && (is_int($check['id']) || is_string($check['id']))) {
                $failed[] = $check['id'];
            }
        }

        return $failed;
    }

    public function batchUnique(string $table, array $checks): array
    {
        $failed = [];
        foreach ($checks as $check) {
            foreach ($this->data[$table] ?? [] as $row) {
                if (!$this->isConflict($check, $row)) {
                    continue;
                }

                $id = $check['id'] ?? null;
                if (is_int($id) || is_string($id)) {
                    $failed[] = $id;
                }
                break;
            }
        }

        return $failed;
    }

    /** @param array<string,mixed> $check @param array<string,mixed> $row */
    private function isConflict(array $check, array $row): bool
    {
        $column = $check['column'] ?? null;
        if (!is_string($column) || !array_key_exists($column, $row) || $row[$column] !== ($check['value'] ?? null)) {
            return false;
        }

        $softDeleteColumn = $check['soft_delete_column'] ?? null;
        if (($check['include_trashed'] ?? true) === false
            && is_string($softDeleteColumn)
            && ($row[$softDeleteColumn] ?? null) !== null) {
            return false;
        }

        $idColumn = is_string($check['id_column'] ?? null) ? $check['id_column'] : 'id';

        return ($check['ignore'] ?? null) === null
            || !array_key_exists($idColumn, $row)
            || $row[$idColumn] !== $check['ignore'];
    }
}
