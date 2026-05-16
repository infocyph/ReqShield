<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

interface Rule
{
    public function cost(): int;

    public function isBatchable(): bool;

    public function message(string $field): string;

    /** @param array<int|string, mixed> $data */
    public function passes(mixed $value, string $field, array $data): bool;
}
