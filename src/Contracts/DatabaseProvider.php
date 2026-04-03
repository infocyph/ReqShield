<?php

namespace Infocyph\ReqShield\Contracts;

interface DatabaseProvider
{
    /**
     * Batch check if values exist.
     *
     * @param string $table Table name
     * @param array $checks Array of check payloads. Each item should include:
     *   - column: string
     *   - value: mixed
     *   - field: string (optional, used by caller for mapping failures)
     *
     * @return array Array of failed check identifiers (usually field names)
     */
    public function batchExistsCheck(string $table, array $checks): array;

    /**
     * Batch check if values are unique.
     *
     * @param string $table Table name
     * @param array $checks Array of check payloads. Each item should include:
     *   - column: string
     *   - value: mixed
     *   - field: string (optional)
     *   - ignore_id: int|null (optional)
     *   - id_column: string (optional)
     *   - with_trashed: bool (optional)
     *   - soft_delete_column: string (optional)
     *
     * @return array Array of failed check identifiers (usually field names)
     */
    public function batchUniqueCheck(string $table, array $checks): array;

    /**
     * Execute a database query.
     *
     * Kept for compatibility with existing providers and custom rule usage.
     * Batch DB validation rules use batchExistsCheck()/batchUniqueCheck().
     *
     * @param string $query The SQL query
     * @param array $params Query parameters
     *
     * @return array Query results
     */
    /**
     * Check if a composite key is unique.
     *
     * @param string $table Table name
     * @param array $columns Array of column => value pairs
     * @param int|null $ignoreId ID to ignore (for updates)
     *
     * @return bool True if unique, false if not
     *
     * @example
     * $provider->compositeUnique('user_roles', ['user_id' => 1, 'role_id' =>
     *     2]);
     */
    public function compositeUnique(
        string $table,
        array $columns,
        ?int $ignoreId = null,
    ): bool;

    /**
     * Check if a value exists in a table.
     *
     * @param string $table Table name
     * @param string $column Column name
     * @param mixed $value Value to check
     * @param int|null $ignoreId ID to ignore (for updates)
     */
    public function exists(
        string $table,
        string $column,
        $value,
        ?int $ignoreId = null,
    ): bool;

    public function query(string $query, array $params = []): array;

}
