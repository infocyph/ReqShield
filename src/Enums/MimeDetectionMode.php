<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Enums;

enum MimeDetectionMode: string
{
    case Compatible = 'compatible';

    case Strict = 'strict';
}
