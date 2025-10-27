<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

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
    protected string $table;

    public function __construct(
        string $table,
        ?string $column = null,
        ?int $ignoreId = null,
        ?string $idColumn = 'id',
    ) {
        $this->table = $table;
        $this->column = $column;
        $this->ignoreId = $ignoreId;
        $this->idColumn = $idColumn;
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

    public function passes($value, string $field, array $data): bool
    {
        // This will be handled by the batch executor
        // Individual execution is only for non-batched scenarios
        if (!$this->db) {
            return true; // Skip if no DB provider
        }

        $column = $this->column ?? $field;
        return $this->db->exists($this->table, $column, $value, $this->ignoreId);
    }

    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }
}
