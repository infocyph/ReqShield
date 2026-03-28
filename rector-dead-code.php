<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $deadCodeConstant = SetList::class . '::DEAD_CODE';
    $rectorConfig->sets([
        defined($deadCodeConstant)
            ? constant($deadCodeConstant)
            : __DIR__ . '/vendor/rector/rector/config/set/dead-code.php',
    ]);
};
