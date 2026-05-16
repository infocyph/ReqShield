<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Contracts\DatabaseProvider;

abstract class AbstractDatabaseTableRule extends BaseRule
{
    protected ?DatabaseProvider $db = null;

    public function __construct(protected string $table) {}

    public function cost(): int
    {
        return 100;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    #[\Override]
    public function isBatchable(): bool
    {
        return true;
    }

    public function setDatabaseProvider(DatabaseProvider $db): void
    {
        $this->db = $db;
    }
}
