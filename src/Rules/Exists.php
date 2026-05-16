<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Exists Rule - Cost: 100
 * Validates that a value exists in a database table.
 * This rule is batchable for performance optimization.
 */
class Exists extends AbstractDatabaseTableRule
{
    public function __construct(
        string $table,
        protected string $column,
    ) {
        parent::__construct($table);
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function message(string $field): string
    {
        return "The selected {$field} is invalid.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        // This will be handled by the batch executor
        if (!$this->db) {
            return true; // Skip if no DB provider
        }

        return $this->db->exists($this->table, $this->column, $value);
    }
}
