<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

interface DatabaseBatchRule extends Rule
{
    /** @return array<string,mixed> */
    public function databasePayload(mixed $value, string $field): array;

    public function operation(): string;

    public function table(): string;
}
