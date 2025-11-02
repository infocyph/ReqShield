<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Contracts\DatabaseProvider;

/**
 * Unique Rule - Cost: 100
 * Validates that a value is unique in a database table.
 * This rule is batchable for performance optimization.
 */
class Unique extends BaseRule
{
    protected ?string $column;

    protected ?DatabaseProvider $db;

    protected ?string $idColumn;

    protected ?int $ignoreId;

    /**
     * Soft delete column name.
     */
    protected string $softDeleteColumn = 'deleted_at';

    protected string $table;

    /**
     * Whether to consider soft deletes.
     */
    protected bool $withTrashed = false;

    public function __construct(
        string $table,
        ?string $column = null,
        ?int $ignoreId = null,
        ?string $idColumn = 'id',
        bool $withTrashed = false,
        string $softDeleteColumn = 'deleted_at',
    ) {
        $this->table = $table;
        $this->column = $column;
        $this->ignoreId = $ignoreId;
        $this->idColumn = $idColumn;
        $this->withTrashed = $withTrashed;
        $this->softDeleteColumn = $softDeleteColumn;
    }

    public function cost(): int
    {
        return 100;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }

    public function getIdColumn(): ?string
    {
        return $this->idColumn;
    }

    public function getIgnoreId(): ?int
    {
        return $this->ignoreId;
    }

    // Getters for batch executor
    public function getTable(): string
    {
        return $this->table;
    }

    public function isBatchable(): bool
    {
        return true;
    }

    public function message(string $field): string
    {
        return "The {$field} has already been taken.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        // This will be handled by the batch executor
        // Individual execution is only for non-batched scenarios
        if (!$this->db) {
            return true; // Skip if no DB provider
        }

        $column = $this->column ?? $field;

        return !$this->db->exists(
            $this->table,
            $column,
            $value,
            $this->ignoreId,
        );
    }

    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }

    /**
     * Check composite unique constraint.
     */
    protected function checkCompositeUnique(
        mixed $value,
        string $field,
        array $data,
    ): bool {
        if (!$this->db) {
            throw new \RuntimeException(
                'Database provider is required for unique rule',
            );
        }

        // Build column => value map
        $columns = [];
        foreach ($this->column as $col) {
            if ($col === $field) {
                $columns[$col] = $value;
            } elseif (isset($data[$col])) {
                $columns[$col] = $data[$col];
            } else {
                // Missing required column for composite unique
                return true; // Pass this rule, other rules will catch missing fields
            }
        }

        return $this->db->compositeUnique(
            $this->table,
            $columns,
            $this->ignoreId,
        );
    }

}
