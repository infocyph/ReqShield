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
    protected ?DatabaseProvider $db = null;

    public function __construct(
        protected string $table,
        protected ?string $column = null,
        protected ?int $ignoreId = null,
        protected ?string $idColumn = 'id',
        /**
         * Whether to consider soft deletes.
         */
        protected bool $withTrashed = false,
        /**
         * Soft delete column name.
         */
        protected string $softDeleteColumn = 'deleted_at',
    ) {}

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

    public function getSoftDeleteColumn(): string
    {
        return $this->softDeleteColumn;
    }

    // Getters for batch executor
    public function getTable(): string
    {
        return $this->table;
    }

    public function getWithTrashed(): bool
    {
        return $this->withTrashed;
    }

    #[\Override]
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

}
