<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Infocyph\DBLayer\Connection\Connection;
use Infocyph\DBLayer\Connection\ConnectionConfig;
use Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider;

$driver = getenv('REQSHIELD_DB_DRIVER');
if (!in_array($driver, ['mysql', 'pgsql'], true)) {
    throw new RuntimeException('REQSHIELD_DB_DRIVER must be mysql or pgsql.');
}

$config = [
    'driver' => $driver,
    'host' => getenv('REQSHIELD_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('REQSHIELD_DB_PORT') ?: ($driver === 'mysql' ? '3306' : '5432')),
    'database' => getenv('REQSHIELD_DB_NAME') ?: 'reqshield',
    'username' => getenv('REQSHIELD_DB_USER') ?: 'reqshield',
    'password' => getenv('REQSHIELD_DB_PASSWORD') ?: '',
];

$connection = new Connection(ConnectionConfig::fromArray($config), 'reqshield-cross-engine-parity');
$table = 'reqshield_typed_parity';

try {
    $connection->statement('DROP TABLE IF EXISTS ' . $table);
    $connection->statement('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, token VARCHAR(40) NOT NULL)');

    foreach (['001', '1', '0', 'alpha', '42'] as $index => $value) {
        $connection->insert('INSERT INTO ' . $table . ' (id, token) VALUES (?, ?)', [$index + 1, $value]);
    }

    $provider = DBLayerDatabaseProvider::fromConnection($connection);
    $values = ['001', 1, '01', '1', 0, '0', 'alpha', 'beta', 42, '42', null];
    $checks = [];

    foreach ($values as $index => $value) {
        $checks[] = [
            'id' => 100 + $index,
            'field' => 'token',
            'column' => 'token',
            'value' => $value,
            'ignore' => 1,
            'id_column' => 'id',
        ];
    }

    foreach (['exists', 'unique'] as $operation) {
        $expected = [];
        foreach ($checks as $check) {
            $failures = $operation === 'exists'
                ? $provider->batchExists($table, [$check])
                : $provider->batchUnique($table, [$check]);
            array_push($expected, ...$failures);
        }

        $actual = $operation === 'exists'
            ? $provider->batchExists($table, $checks)
            : $provider->batchUnique($table, $checks);

        sort($expected);
        sort($actual);
        if ($actual !== $expected) {
            throw new RuntimeException(sprintf(
                '%s parity failed for %s. Expected: %s; actual: %s',
                $operation,
                $driver,
                json_encode($expected, JSON_THROW_ON_ERROR),
                json_encode($actual, JSON_THROW_ON_ERROR),
            ));
        }
    }

    $engineVersion = $connection->scalar('SELECT VERSION()');
    if (!is_scalar($engineVersion)) {
        throw new RuntimeException('Engine version query did not return a scalar.');
    }

    fwrite(STDOUT, sprintf(
        "PASS %s SQL comparison parity (%d candidates, exists and unique). Engine: %s\n",
        $driver,
        count($checks),
        (string) $engineVersion,
    ));
} finally {
    try {
        $connection->statement('DROP TABLE IF EXISTS ' . $table);
    } finally {
        $connection->disconnect();
    }
}
