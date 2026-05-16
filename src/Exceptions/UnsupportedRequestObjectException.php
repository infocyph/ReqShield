<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

final class UnsupportedRequestObjectException extends \InvalidArgumentException
{
    public static function missingRequestAccessors(): self
    {
        return new self(
            'Unsupported request object. Expected at least one of: getQueryParams(), getParsedBody(), getUploadedFiles(), getAttributes().',
        );
    }
}
