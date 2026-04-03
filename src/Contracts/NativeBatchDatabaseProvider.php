<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Contracts;

/**
 * @deprecated All DatabaseProvider implementations are treated as
 * native-batch providers. This marker remains for backwards compatibility.
 */
interface NativeBatchDatabaseProvider extends DatabaseProvider
{
}
