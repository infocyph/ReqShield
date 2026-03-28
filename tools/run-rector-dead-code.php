<?php

declare(strict_types=1);

putenv('REQSHIELD_RECTOR_DEAD_CODE=1');

$command = implode(' ', [
    escapeshellarg(PHP_BINARY),
    escapeshellarg(__DIR__ . '/../vendor/bin/rector'),
    'process',
    '--dry-run',
    '--config',
    escapeshellarg(__DIR__ . '/../rector.php'),
]);

passthru($command, $exitCode);

exit($exitCode);
