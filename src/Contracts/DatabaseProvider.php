<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

interface DatabaseProvider
{
    /**
     * @param array<int|string, mixed> $checks
     * @return array<int, int|string>
     */
    public function batchExistsCheck(string $table, array $checks): array;

    /**
     * @param array<int|string, mixed> $checks
     * @return array<int, int|string>
     */
    public function batchUniqueCheck(string $table, array $checks): array;

    /** @param array<string, mixed> $columns */
    public function compositeUnique(
        string $table,
        array $columns,
        ?int $ignoreId = null,
    ): bool;

    public function exists(
        string $table,
        string $column,
        mixed $value,
        ?int $ignoreId = null,
    ): bool;

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $query, array $params = []): array;
}
