<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

interface DatabaseBatchRule extends Rule
{
    /**
     * Build the rule-owned payload. BatchExecutor prepends the distinct integer
     * correlation id before the payload crosses the DatabaseProvider boundary.
     *
     * @return array<string,mixed>
     */
    public function databasePayload(mixed $value, string $field): array;

    public function operation(): string;

    public function table(): string;
}
