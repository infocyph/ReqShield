<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures;

final class ReqShieldCtorDto
{
    public function __construct(
        public int $age,
        public bool $active,
    ) {
    }
}
