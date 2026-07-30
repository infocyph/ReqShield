<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures;

enum ReqShieldStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
