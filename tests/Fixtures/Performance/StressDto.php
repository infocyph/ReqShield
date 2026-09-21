<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures\Performance;

final readonly class StressDto
{
    public function __construct(public string $value) {}
}
