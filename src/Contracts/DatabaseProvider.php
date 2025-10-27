<?php

namespace Infocyph\ReqShield\Contracts;

interface DatabaseProvider
{
    /**
     * Batch check if values exist.
     *
     * @param  string  $table  Table name
     * @param  array  $checks  Array of ['column' => value] checks
     * @return array Array of values that don't exist
     */
    public function batchExistsCheck(string $table, array $checks): array;

    /**
     * Batch check if values are unique.
     *
     * @param  string  $table  Table name
     * @param  array  $checks  Array of ['column' => value] checks
     * @return array Array of non-unique values
     */
    public function batchUniqueCheck(string $table, array $checks): array;

    /**
     * Check if a value exists in a table.
     *
     * @param  string  $table  Table name
     * @param  string  $column  Column name
     * @param  mixed  $value  Value to check
     * @param  int|null  $ignoreId  ID to ignore (for updates)
     */
    public function exists(string $table, string $column, $value, ?int $ignoreId = null): bool;

    /**
     * Execute a database query.
     *
     * @param  string  $query  The SQL query
     * @param  array  $params  Query parameters
     * @return array Query results
     */
    public function query(string $query, array $params = []): array;
}
