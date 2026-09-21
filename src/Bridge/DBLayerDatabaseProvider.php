<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Bridge;

use Closure;
use Infocyph\DBLayer\Connection\Connection;
use Infocyph\DBLayer\Query\QueryBuilder;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

final readonly class DBLayerDatabaseProvider implements DatabaseProvider
{
    /**
     * @param Closure():Connection $connection
     */
    public function __construct(private Closure $connection) {}

    public static function fromConnection(Connection $connection): self
    {
        return new self(static fn(): Connection => $connection);
    }

    public function batchExists(string $table, array $checks): array
    {
        $connection = $this->resolveConnection();
        $table = $this->sqlIdentifier($table, 'table');
        $failed = [];

        foreach ($this->groupChecks($checks, ['column']) as $group) {
            $found = $this->matchedRows($connection, $table, $group, false);

            foreach ($group as $check) {
                if (!isset($found[$this->valueKey($check['value'])])) {
                    $failed[] = $check['id'];
                }
            }
        }

        return $failed;
    }

    public function batchUnique(string $table, array $checks): array
    {
        $connection = $this->resolveConnection();
        $table = $this->sqlIdentifier($table, 'table');
        $failed = [];

        foreach ($this->groupChecks(
            $checks,
            ['column', 'ignore', 'id_column', 'include_trashed', 'soft_delete_column'],
        ) as $group) {
            $rowsByValue = $this->matchedRows($connection, $table, $group, true);

            foreach ($group as $check) {
                if (($rowsByValue[$this->valueKey($check['value'])] ?? []) !== []) {
                    $failed[] = $check['id'];
                }
            }
        }

        return $failed;
    }

    /**
     * @param list<array{
     *   id:int,field:string,column:string,value:mixed,ignore?:mixed,id_column?:string,
     *   include_trashed?:bool,soft_delete_column?:string|null
     * }> $checks
     * @param list<string> $keys
     * @return list<list<array{
     *   id:int,field:string,column:string,value:mixed,ignore?:mixed,id_column?:string,
     *   include_trashed?:bool,soft_delete_column?:string|null
     * }>>
     */
    private function groupChecks(array $checks, array $keys): array
    {
        $groups = [];

        foreach ($checks as $check) {
            $parts = [];
            foreach ($keys as $key) {
                $parts[] = $this->valueKey($check[$key] ?? null);
            }

            $groups[implode('|', $parts)][] = $check;
        }

        return array_values($groups);
    }

    private function excludeIgnoredRow(QueryBuilder $query, string $idColumn, mixed $ignore): void
    {
        $query->where(static function (QueryBuilder $nested) use ($idColumn, $ignore): void {
            $nested->where($idColumn, '!=', $ignore)
                ->whereNull($idColumn, 'or');
        });
    }

    /**
     * @param list<array{
     *   id:int,field:string,column:string,value:mixed,ignore?:mixed,id_column?:string,
     *   include_trashed?:bool,soft_delete_column?:string|null
     * }> $checks
     * @return array<string,mixed>
     */
    private function matchedRows(
        Connection $connection,
        string $table,
        array $checks,
        bool $unique,
    ): array {
        $first = $checks[0];
        $column = $this->sqlIdentifier($first['column'], 'column');
        $idColumn = $this->sqlIdentifier($first['id_column'] ?? 'id', 'id column');
        $softDeleteColumn = $first['soft_delete_column'] ?? null;
        if ($softDeleteColumn !== null) {
            $softDeleteColumn = $this->sqlIdentifier($softDeleteColumn, 'soft-delete column');
        }

        $includeTrashed = ($first['include_trashed'] ?? true) === true;
        $ignoreEnabled = $unique
            && array_key_exists('ignore', $first)
            && $first['ignore'] !== null;
        $fixedBindings = $ignoreEnabled ? 1 : 0;
        $values = [];
        $hasNull = false;

        foreach ($checks as $check) {
            $value = $check['value'];
            if ($value === null) {
                $hasNull = true;

                continue;
            }

            $values[$this->valueKey($value)] = $value;
        }

        $rows = [];
        if ($values !== []) {
            $chunkSize = $connection->safeBatchSize(
                parametersPerRow: 1,
                fixedBindings: $fixedBindings,
                requested: count($values),
            );
            foreach (array_chunk(array_values($values), $chunkSize) as $chunk) {
                $query = $this->baseQuery(
                    $connection,
                    $table,
                    $column,
                    $unique,
                    $idColumn,
                    $softDeleteColumn,
                    $includeTrashed,
                    $ignoreEnabled,
                    $first['ignore'] ?? null,
                )->whereIn($column, $chunk);

                foreach ($query->get() as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }

        if ($hasNull) {
            $query = $this->baseQuery(
                $connection,
                $table,
                $column,
                $unique,
                $idColumn,
                $softDeleteColumn,
                $includeTrashed,
                $ignoreEnabled,
                $first['ignore'] ?? null,
            )->whereNull($column);

            foreach ($query->get() as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->valueKey($row[$column] ?? null);
            if ($unique) {
                $indexed[$key][] = $row;
            } else {
                $indexed[$key] = true;
            }
        }

        return $indexed;
    }

    private function baseQuery(
        Connection $connection,
        string $table,
        string $column,
        bool $unique,
        string $idColumn,
        ?string $softDeleteColumn,
        bool $includeTrashed,
        bool $ignoreEnabled,
        mixed $ignore,
    ): QueryBuilder {
        $select = $unique ? [$column, $idColumn] : [$column];
        if ($unique && $softDeleteColumn !== null) {
            $select[] = $softDeleteColumn;
        }

        $query = $connection->table($table)->select($select);

        if ($unique && !$includeTrashed && $softDeleteColumn !== null) {
            $query->whereNull($softDeleteColumn);
        }
        if ($ignoreEnabled) {
            $this->excludeIgnoredRow($query, $idColumn, $ignore);
        }

        return $query;
    }

    private function resolveConnection(): Connection
    {
        return $this->validatedConnection(($this->connection)());
    }

    private function validatedConnection(mixed $connection): Connection
    {
        if (!$connection instanceof Connection) {
            throw new \UnexpectedValueException(
                'DBLayer connection resolver must return a Connection instance.',
            );
        }

        return $connection;
    }

    /** @return non-empty-string */
    private function sqlIdentifier(string $identifier, string $type): string
    {
        $identifier = trim($identifier);
        if (
            $identifier === ''
            || preg_match(
                '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*\z/D',
                $identifier,
            ) !== 1
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Database validation %s names must be dotted SQL identifiers.',
                $type,
            ));
        }

        return $identifier;
    }

    private function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            $value = (int) $value;
        }

        if (is_scalar($value)) {
            return 'scalar:' . (string) $value;
        }

        return get_debug_type($value) . ':' . serialize($value);
    }
}
