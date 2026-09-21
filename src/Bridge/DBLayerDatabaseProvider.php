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
    public function __construct(private Closure $connection, private int $maxBatchValues = 128)
    {
        if ($maxBatchValues < 1) {
            throw new \InvalidArgumentException('Maximum database batch values must be positive.');
        }
    }

    public static function fromConnection(Connection $connection, int $maxBatchValues = 128): self
    {
        return new self(static fn(): Connection => $connection, $maxBatchValues);
    }

    public function batchExists(string $table, array $checks): array
    {
        $connection = $this->resolveConnection();
        $table = $this->sqlIdentifier($table, 'table');
        $failed = [];

        foreach ($this->groupChecks($checks, ['column']) as $group) {
            $found = $this->matchedValues($connection, $table, $group, false);

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
            $found = $this->matchedValues($connection, $table, $group, true);

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
        $query = $connection->table($table)->select($column);

        if ($unique && !$includeTrashed && $softDeleteColumn !== null) {
            $query->whereNull($softDeleteColumn);
        }
        if ($ignoreEnabled) {
            $this->excludeIgnoredRow($query, $idColumn, $ignore);
        }

        return $query;
    }

    /**
     * @param non-empty-string $table
     * @param GroupConfig $config
     */
    private function candidateQuery(
        Connection $connection,
        string $table,
        array $config,
        bool $unique,
        mixed $value,
    ): QueryBuilder {
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
        );

        return $value === null
            ? $query->whereNull($config['column'])
            : $query->where($config['column'], '=', $value);
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

            $groups[serialize($parts)][] = $check;
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
     * @param non-empty-string $table
     * @param list<mixed> $values
     * @param GroupConfig $config
     * @return array<string,true>
     */
    private function matchCandidates(
        Connection $connection,
        string $table,
        array $values,
        array $config,
        bool $unique,
    ): array {
        if ($values === []) {
            return [];
        }

        if (count($values) === 1 || ($connection->getConfig()->securityConfig()['raw_sql_policy'] ?? 'allow') !== 'allow') {
            return $this->matchRestrictedCandidates($connection, $table, $values, $config, $unique);
        }

        $chunkSize = $connection->safeBatchSize(
            parametersPerRow: $config['ignore_enabled'] ? 2 : 1,
            requested: min(count($values), $this->maxBatchValues),
        );
        $candidate = $this->candidateQuery($connection, $table, $config, $unique, $values[0]);
        $sql = $candidate->toSql();
        $prefixBindings = array_slice($candidate->getBindings(), 0, -1);
        $found = [];
        foreach (array_chunk($values, $chunkSize) as $chunk) {
            $row = $this->matchProjection($connection, $sql, $prefixBindings, $chunk);
            foreach ($chunk as $index => $value) {
                if (in_array($row['match_' . $index], [1, '1'], true)) {
                    $found[$this->valueKey($value)] = true;
                }
            }
        }

        return $found;
    }

    /**
     * @param non-empty-string $table
     * @param non-empty-list<Check> $checks
     * @return array<string,true>
     */
    private function matchedValues(Connection $connection, string $table, array $checks, bool $unique): array
    {
        $config = $this->groupConfig($checks, $unique);
        [$values, $hasNull] = $this->candidateValues($checks);
        $found = $this->matchCandidates($connection, $table, $values, $config, $unique);

        if ($hasNull && $this->candidateQuery($connection, $table, $config, $unique, null)->limit(1)->get() !== []) {
            $found[$this->valueKey(null)] = true;
        }

        return $found;
    }

    /**
     * @param list<mixed> $prefixBindings
     * @param list<mixed> $values
     * @return array<string,mixed>
     */
    private function matchProjection(Connection $connection, string $sql, array $prefixBindings, array $values): array
    {
        $columns = [];
        $bindings = [];
        foreach ($values as $index => $value) {
            $columns[] = 'CASE WHEN EXISTS (' . $sql . ') THEN 1 ELSE 0 END AS match_' . $index;
            array_push($bindings, ...$prefixBindings);
            $bindings[] = $value;
        }

        return $connection->query()->selectRaw(implode(', ', $columns), $bindings)->get()[0];
    }

    /**
     * @param non-empty-string $table
     * @param list<mixed> $values
     * @param GroupConfig $config
     * @return array<string,true>
     */
    private function matchRestrictedCandidates(
        Connection $connection,
        string $table,
        array $values,
        array $config,
        bool $unique,
    ): array {
        $found = [];
        foreach ($values as $value) {
            if ($this->candidateQuery($connection, $table, $config, $unique, $value)->limit(1)->get() !== []) {
                $found[$this->valueKey($value)] = true;
            }
        }

        return $found;
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

        return get_debug_type($value) . ':' . serialize($value);
    }
}
