<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

interface DatabaseProvider
{
    /**
     * @param list<array{
     *   id:int,
     *   field:string,
     *   column:string,
     *   value:mixed,
     *   ignore?:mixed,
     *   id_column?:string,
     *   include_trashed?:bool,
     *   soft_delete_column?:string|null
     * }> $checks
     * @return list<int>
     */
    public function batchExists(string $table, array $checks): array;

    /**
     * @param list<array{
     *   id:int,
     *   field:string,
     *   column:string,
     *   value:mixed,
     *   ignore?:mixed,
     *   id_column?:string,
     *   include_trashed?:bool,
     *   soft_delete_column?:string|null
     * }> $checks
     * @return list<int>
     */
    public function batchUnique(string $table, array $checks): array;
}
