<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Exists Rule - Cost: 100
 * Validates that a value exists in a database table.
 * This rule is batchable for performance optimization.
 */
final class Exists extends AbstractDatabaseTableRule
{
    public function __construct(
        string $table,
        protected string $column,
    ) {
        parent::__construct($table);
    }

    public function column(): string
    {
        return $this->column;
    }

    public function databasePayload(mixed $value, string $field): array
    {
        return ['field' => $field, 'column' => $this->column, 'value' => $value];
    }

    public function message(string $field): string
    {
        return "The selected {$field} is invalid.";
    }

    public function operation(): string
    {
        return 'exists';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return true;
    }

    public function table(): string
    {
        return $this->table;
    }
}
