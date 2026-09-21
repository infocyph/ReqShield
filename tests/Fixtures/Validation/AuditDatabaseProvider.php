<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures\Validation;

use Infocyph\ReqShield\Contracts\DatabaseProvider;

final class AuditDatabaseProvider implements DatabaseProvider
{
    /** @var list<int> */
    public array $ids = [];

    public function __construct(private readonly bool $returnUnknownId = false) {}

    public function batchExists(string $table, array $checks): array
    {
        return $this->response($table, $checks);
    }

    public function batchUnique(string $table, array $checks): array
    {
        return $this->response($table, $checks);
    }

    /**
     * @param list<array{
     *   id:int,field:string,column:string,value:mixed,ignore?:mixed,id_column?:string,
     *   include_trashed?:bool,soft_delete_column?:string|null
     * }> $checks
     * @return list<int>
     */
    private function response(string $table, array $checks): array
    {
        if ($table === '') {
            throw new \InvalidArgumentException('Database table cannot be empty.');
        }

        $this->ids = array_column($checks, 'id');

        return $this->returnUnknownId ? [999999] : [];
    }
}
