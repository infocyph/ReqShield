<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Bridge;

use Closure;
use Infocyph\DBLayer\Connection\Connection;
use Infocyph\DBLayer\Query\QueryBuilder;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

/**
 * @phpstan-type Check array{
 *   id:int,field:string,column:string,value:mixed,ignore?:mixed,id_column?:string,
 *   include_trashed?:bool,soft_delete_column?:string|null
 * }
 * @phpstan-type GroupConfig array{
 *   column:non-empty-string,id_column:non-empty-string,
 *   soft_delete_column:non-empty-string|null,include_trashed:bool,
 *   ignore_enabled:bool,ignore:mixed
 * }
 */
final readonly class DBLayerDatabaseProvider implements DatabaseProvider
{
    /** @param Closure():Connection $connection */
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
            $found = $this->matchedExists($connection, $table, $group);

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
            $found = $this->matchedUnique($connection, $table, $group);

            foreach ($group as $check) {
                if (isset($found[$this->valueKey($check['value'])])) {
                    $failed[] = $check['id'];
                }
            }
        }

        return $failed;
    }

    /**
     * @param non-empty-string $table
     * @param non-empty-string $column
     * @param non-empty-string $idColumn
     * @param non-empty-string|null $softDeleteColumn
     */
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

    /**
     * @param list<Check> $checks
     * @return array{0:list<mixed>,1:bool}
     */
    private function candidateValues(array $checks): array
    {
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

        return [array_values($values), $hasNull];
    }

    /** @param non-empty-string $idColumn */
    private function excludeIgnoredRow(QueryBuilder $query, string $idColumn, mixed $ignore): void
    {
        $query->where(static function (QueryBuilder $nested) use ($idColumn, $ignore): void {
            $nested->where($idColumn, '!=', $ignore)
                ->whereNull($idColumn, 'or');
        });
    }

    /**
     * @param non-empty-string $table
     * @param list<mixed> $values
     * @param GroupConfig $config
     * @return list<array<string,mixed>>
     */
    private function fetchNonNullRows(
        Connection $connection,
        string $table,
        array $values,
        array $config,
        bool $unique,
    ): array {
        if ($values === []) {
            return [];
        }

        $chunkSize = $connection->safeBatchSize(
            parametersPerRow: 1,
            fixedBindings: $config['ignore_enabled'] ? 1 : 0,
            requested: count($values),
        );
        $rows = [];

        foreach (array_chunk($values, $chunkSize) as $chunk) {
            $query = $this->baseQuery(
                $connection,
                $table,
                $config['column'],
                $unique,
                $config['id_column'],
                $config['soft_delete_column'],
                $config['include_trashed'],
                $config['ignore_enabled'],
                $config['ignore'],
            )->whereIn($config['column'], $chunk);

            array_push($rows, ...$query->get());
        }

        return $rows;
    }

    /**
     * @param non-empty-string $table
     * @param GroupConfig $config
     * @return list<array<string,mixed>>
     */
    private function fetchNullRows(
        Connection $connection,
        string $table,
        array $config,
        bool $unique,
    ): array {
        return $this->baseQuery(
            $connection,
            $table,
            $config['column'],
            $unique,
            $config['id_column'],
            $config['soft_delete_column'],
            $config['include_trashed'],
            $config['ignore_enabled'],
            $config['ignore'],
        )->whereNull($config['column'])->get();
    }

    /**
     * @param list<Check> $checks
     * @param list<string> $keys
     * @return list<non-empty-list<Check>>
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

    /**
     * @param non-empty-list<Check> $checks
     * @return GroupConfig
     */
    private function groupConfig(array $checks, bool $unique): array
    {
        $first = $checks[0];
        $softDeleteColumn = $first['soft_delete_column'] ?? null;

        return [
            'column' => $this->sqlIdentifier($first['column'], 'column'),
            'id_column' => $this->sqlIdentifier($first['id_column'] ?? 'id', 'id column'),
            'soft_delete_column' => $softDeleteColumn === null
                ? null
                : $this->sqlIdentifier($softDeleteColumn, 'soft-delete column'),
            'include_trashed' => ($first['include_trashed'] ?? true) === true,
            'ignore_enabled' => $unique
                && array_key_exists('ignore', $first)
                && $first['ignore'] !== null,
            'ignore' => $first['ignore'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-string $column
     * @return array<string,true>
     */
    private function indexExistsRows(array $rows, string $column): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$this->valueKey($row[$column] ?? null)] = true;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-string $column
     * @return array<string,list<array<string,mixed>>>
     */
    private function indexUniqueRows(array $rows, string $column): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $key = $this->valueKey($row[$column] ?? null);
            $indexed[$key] ??= [];
            $indexed[$key][] = $row;
        }

        return $indexed;
    }

    /**
     * @param non-empty-string $table
     * @param non-empty-list<Check> $checks
     * @return array<string,true>
     */
    private function matchedExists(Connection $connection, string $table, array $checks): array
    {
        $config = $this->groupConfig($checks, false);
        [$values, $hasNull] = $this->candidateValues($checks);
        $rows = $this->fetchNonNullRows($connection, $table, $values, $config, false);

        if ($hasNull) {
            array_push($rows, ...$this->fetchNullRows($connection, $table, $config, false));
        }

        return $this->indexExistsRows($rows, $config['column']);
    }

    /**
     * @param non-empty-string $table
     * @param non-empty-list<Check> $checks
     * @return array<string,list<array<string,mixed>>>
     */
    private function matchedUnique(Connection $connection, string $table, array $checks): array
    {
        $config = $this->groupConfig($checks, true);
        [$values, $hasNull] = $this->candidateValues($checks);
        $rows = $this->fetchNonNullRows($connection, $table, $values, $config, true);

        if ($hasNull) {
            array_push($rows, ...$this->fetchNullRows($connection, $table, $config, true));
        }

        return $this->indexUniqueRows($rows, $config['column']);
    }

    private function resolveConnection(): Connection
    {
        return $this->validatedConnection(($this->connection)());
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

    private function validatedConnection(mixed $connection): Connection
    {
        if (!$connection instanceof Connection) {
            throw new \UnexpectedValueException(
                'DBLayer connection resolver must return a Connection instance.',
            );
        }

        return $connection;
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
            return 'scalar:' . $value;
        }

        return get_debug_type($value) . ':' . serialize($value);
    }
}
