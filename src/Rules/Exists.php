<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Exists Rule - Cost: 100
 * Validates that a value exists in a database table.
 * This rule is batchable for performance optimization.
 */
class Exists extends BaseRule
{
    protected string $column;
    protected ?DatabaseProvider $db;
    protected string $table;

    public function __construct(string $table, string $column)
    {
        $this->table = $table;
        $this->column = $column;
    }

    public function cost(): int
    {
        return 100;
    }

    public function getColumn(): string
    {
        return $this->column;
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
        return "The selected {$field} is invalid.";
    }

    public function passes($value, string $field, array $data): bool
    {
        // This will be handled by the batch executor
        if (!$this->db) {
            return true; // Skip if no DB provider
        }

        return $this->db->exists($this->table, $this->column, $value);
    }

    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }
}
