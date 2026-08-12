<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Unique Rule - Cost: 100
 * Validates that a value is unique in a database table.
 * This rule is batchable for performance optimization.
 */
final class Unique extends AbstractDatabaseTableRule
{
    public function __construct(
        string $table,
        protected string $column,
        protected mixed $ignoreId = null,
        protected string $idColumn = 'id',
        /**
         * Whether to consider soft deletes.
         */
        protected bool $withTrashed = true,
        /**
         * Soft delete column name.
         */
        protected ?string $softDeleteColumn = null,
    ) {
        parent::__construct($table);
    }

    public function column(): string
    {
        return $this->column;
    }

    public function databasePayload(mixed $value, string $field): array
    {
        return [
            'field' => $field,
            'column' => $this->column,
            'value' => $value,
            'ignore' => $this->ignoreId,
            'id_column' => $this->idColumn,
            'include_trashed' => $this->withTrashed,
            'soft_delete_column' => $this->softDeleteColumn,
        ];
    }

    public function idColumn(string $column): self
    {
        $clone = clone $this;
        $clone->idColumn = $column;

        return $clone;
    }

    public function idColumnName(): string
    {
        return $this->idColumn;
    }

    public function ignore(mixed $id): self
    {
        $clone = clone $this;
        $clone->ignoreId = $id;

        return $clone;
    }

    public function ignoredValue(): mixed
    {
        return $this->ignoreId;
    }

    public function includeTrashed(): bool
    {
        return $this->withTrashed;
    }

    public function message(string $field): string
    {
        return "The {$field} has already been taken.";
    }

    public function operation(): string
    {
        return 'unique';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return true;
    }

    public function softDeleteColumn(): ?string
    {
        return $this->softDeleteColumn;
    }

    public function table(): string
    {
        return $this->table;
    }

    public function withoutTrashed(string $column = 'deleted_at'): self
    {
        $clone = clone $this;
        $clone->withTrashed = false;
        $clone->softDeleteColumn = $column;

        return $clone;
    }

    public function withTrashed(): self
    {
        $clone = clone $this;
        $clone->withTrashed = true;
        $clone->softDeleteColumn = null;

        return $clone;
    }
}
