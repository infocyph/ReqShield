<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Unique Rule - Cost: 100
 * Validates that a value is unique in a database table.
 * This rule is batchable for performance optimization.
 */
class Unique extends AbstractDatabaseTableRule
{
    public function __construct(
        string $table,
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
    ) {
        parent::__construct($table);
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

    public function getWithTrashed(): bool
    {
        return $this->withTrashed;
    }

    public function message(string $field): string
    {
        return "The {$field} has already been taken.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
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
}
