<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

interface DatabaseProvider
{
    /**
     * @param list<array<string, mixed>> $checks
     * @return list<int|string>
     */
    public function batchExists(string $table, array $checks): array;

    /**
     * @param list<array<string, mixed>> $checks
     * @return list<int|string>
     */
    public function batchUnique(string $table, array $checks): array;
}
