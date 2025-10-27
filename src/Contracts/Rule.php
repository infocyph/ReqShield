<?php

namespace Infocyph\ReqShield\Contracts;

interface Rule
{
    /**
     * Get the cost of this rule (for optimization).
     * Lower cost rules run first.
     *
     * Cost guidelines:
     * - 1-10: Simple checks (type checks, empty checks)
     * - 10-50: Medium complexity (string operations, regex)
     * - 100+: Expensive operations (database queries, API calls)
     */
    public function cost(): int;

    /**
     * Whether this rule can be batched with others.
     * Used for expensive operations like database queries.
     */
    public function isBatchable(): bool;

    /**
     * Get the validation error message.
     *
     * @param  string  $field  The field name
     */
    public function message(string $field): string;

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value  The value being validated
     * @param  string  $field  The field name
     * @param  array  $data  All data being validated
     */
    public function passes(mixed $value, string $field, array $data): bool;
}
