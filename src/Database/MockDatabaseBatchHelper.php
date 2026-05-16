<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Database;

/**
 * @phpstan-type Row array<string, mixed>
 * @phpstan-type UniqueCheck array{
 *   column:string,
 *   value:mixed,
 *   identifier:int|string,
 *   ignore_id:int|null,
 *   id_column:string,
 *   with_trashed:bool,
 *   soft_delete_column:string
 * }
 */
final class MockDatabaseBatchHelper
{
    /**
     * @param array<int, Row> $rows
     * @param UniqueCheck $check
     */
    public static function hasUniqueConflict(array $rows, array $check): bool
    {
        foreach ($rows as $row) {
            if (self::shouldSkipUniqueRow($row, $check)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** @param array<int|string, mixed> $checks */
    public static function looksLikeStructuredBatch(array $checks): bool
    {
        if ($checks === []) {
            return true;
        }

        $first = array_values($checks)[0] ?? null;

        return is_array($first) && array_key_exists('column', $first);
    }

    public static function normalizeFailedIdentifier(mixed $value, int|string $fallback): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $fallback;
    }

    /**
     * @param array<int|string, mixed> $check
     * @return UniqueCheck
     */
    public static function normalizeUniqueCheck(array $check): array
    {
        $value = $check['value'] ?? null;
        $identifier = $check['field'] ?? $value;
        $identifier = is_int($identifier) || is_string($identifier)
            ? $identifier
            : self::stringIdentifier($identifier);

        return [
            'column' => self::stringIdentifier($check['column'] ?? null),
            'value' => $value,
            'identifier' => $identifier,
            'ignore_id' => isset($check['ignore_id']) && is_int($check['ignore_id']) ? $check['ignore_id'] : null,
            'id_column' => isset($check['id_column']) && is_string($check['id_column']) ? $check['id_column'] : 'id',
            'with_trashed' => isset($check['with_trashed']) && is_bool($check['with_trashed']) ? $check['with_trashed'] : true,
            'soft_delete_column' => isset($check['soft_delete_column']) && is_string($check['soft_delete_column']) ? $check['soft_delete_column'] : 'deleted_at',
        ];
    }

    public static function stringIdentifier(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    protected static function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param Row $row
     * @param UniqueCheck $check
     */
    protected static function shouldSkipUniqueRow(array $row, array $check): bool
    {
        if (
            !$check['with_trashed']
            && array_key_exists($check['soft_delete_column'], $row)
            && $row[$check['soft_delete_column']] !== null
        ) {
            return true;
        }

        if (!array_key_exists($check['column'], $row) || $row[$check['column']] !== $check['value']) {
            return true;
        }

        return $check['ignore_id'] !== null
            && isset($row[$check['id_column']])
            && self::intValue($row[$check['id_column']]) === $check['ignore_id'];
    }
}
