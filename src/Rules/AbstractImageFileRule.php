<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractImageFileRule extends BaseRule
{
    /** @return array{0: int, 1: int, 2: int, 3: string}|false */
    protected function getImageInfo(mixed $value): array|false
    {
        $path = $this->getUploadedFilePath($value);
        if ($path === null) {
            return false;
        }

        set_error_handler(static fn() => true);

        try {
            return getimagesize($path);
        } finally {
            restore_error_handler();
        }
    }
}
