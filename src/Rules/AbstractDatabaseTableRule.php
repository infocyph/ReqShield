<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Contracts\DatabaseBatchRule;

abstract class AbstractDatabaseTableRule extends BaseRule implements DatabaseBatchRule
{
    public function __construct(protected string $table) {}

    public function cost(): int
    {
        return 100;
    }
}
