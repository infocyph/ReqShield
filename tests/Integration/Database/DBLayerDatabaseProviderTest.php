<?php

declare(strict_types=1);

use Infocyph\DBLayer\DB;
use Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;
use Infocyph\ReqShield\Rule;
use Infocyph\ReqShield\Validator;

beforeEach(function () {
    DB::resetRuntimeState();
    expect(DB::getConnections())->toBe([]);

    $this->connection = DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ], 'reqshield-tests');

    $this->connection->statement(
        'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, username TEXT, tenant_id INTEGER, deleted_at TEXT NULL)',
    );
    $this->connection->statement(
        'CREATE TABLE teams (id INTEGER PRIMARY KEY, code TEXT, name TEXT)',
    );
    $this->connection->statement(
        'CREATE TABLE edge_values (id INTEGER PRIMARY KEY, token TEXT NULL, deleted_at TEXT NULL)',
    );
    $this->connection->statement(
        'CREATE TABLE subscriptions (id TEXT PRIMARY KEY, legacy_id TEXT, email TEXT, removed_at TEXT NULL)',
    );
    $this->connection->insert(
        'INSERT INTO users (id, email, username, tenant_id, deleted_at) VALUES (?, ?, ?, ?, ?)',
        [0, 'zero@example.com', 'zero', 0, null],
    );
    $this->connection->insert(
        'INSERT INTO users (id, email, username, tenant_id, deleted_at) VALUES (?, ?, ?, ?, ?)',
        [1, 'alice@example.com', 'alice', 10, null],
    );
    $this->connection->insert(
        'INSERT INTO users (id, email, username, tenant_id, deleted_at) VALUES (?, ?, ?, ?, ?)',
        [2, 'deleted@example.com', 'deleted', 10, '2025-01-01'],
    );
    $this->connection->insert(
        'INSERT INTO teams (id, code, name) VALUES (?, ?, ?)',
        [10, 'core', 'Core'],
    );
    $this->connection->insert(
        'INSERT INTO edge_values (id, token, deleted_at) VALUES (?, ?, ?)',
        [1, '0', null],
    );
    $this->connection->insert(
        'INSERT INTO edge_values (id, token, deleted_at) VALUES (?, ?, ?)',
        [2, null, null],
    );
    $this->connection->insert(
        'INSERT INTO edge_values (id, token, deleted_at) VALUES (?, ?, ?)',
        [3, 'alpha', null],
    );
    $this->connection->insert(
        'INSERT INTO subscriptions (id, legacy_id, email, removed_at) VALUES (?, ?, ?, ?)',
        ['account-1', '0', 'archived@example.com', '2025-01-01'],
    );
    $this->connection->insert(
        'INSERT INTO subscriptions (id, legacy_id, email, removed_at) VALUES (?, ?, ?, ?)',
        ['account-2', null, 'nullable-id@example.com', null],
    );
});

afterEach(function () {
    DB::resetRuntimeState();
    expect(DB::getConnections())->toBe([]);
});

test('DBLayer SQLite provider satisfies exists and unique semantics', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $validator = Validator::make([
        'team_id' => Rule::exists('teams', 'id'),
        'email' => Rule::unique('users', 'email'),
    ], $provider)->setFailFast(false);

    expect($validator->validate([
        'team_id' => 10,
        'email' => 'new@example.com',
    ])->passes())->toBeTrue();

    $failed = $validator->validate([
        'team_id' => 999,
        'email' => 'alice@example.com',
    ]);

    expect($failed->errors())->toHaveKeys(['team_id', 'email']);
});

test('DBLayer SQLite provider handles ignore and soft deletes', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);

    expect(Validator::make([
        'email' => Rule::unique('users', 'email')->ignore(1),
    ], $provider)->validate(['email' => 'alice@example.com'])->passes())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('users', 'email')->withoutTrashed(),
    ], $provider)->validate(['email' => 'deleted@example.com'])->passes())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('users', 'email')->withTrashed(),
    ], $provider)->validate(['email' => 'deleted@example.com'])->fails())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('users', 'email')->idColumn('tenant_id')->ignore(10),
    ], $provider)->validate(['email' => 'alice@example.com'])->passes())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('users', 'email')->ignore('1'),
    ], $provider)->validate(['email' => 'alice@example.com'])->passes())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('users', 'email')->ignore(0),
    ], $provider)->validate(['email' => 'zero@example.com'])->passes())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('subscriptions', 'email')->idColumn('legacy_id')->ignore('0'),
    ], $provider)->validate(['email' => 'archived@example.com'])->passes())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('subscriptions', 'email')->idColumn('legacy_id')->ignore('0'),
    ], $provider)->validate(['email' => 'nullable-id@example.com'])->fails())->toBeTrue();

    expect(Validator::make([
        'email' => Rule::unique('subscriptions', 'email')->withoutTrashed('removed_at'),
    ], $provider)->validate(['email' => 'archived@example.com'])->passes())->toBeTrue();
});

test('DBLayer SQLite provider batches wildcard checks', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $validator = Validator::make([
        'contacts.*.team_id' => 'required|exists:teams,id',
    ], $provider);
    $this->connection->resetStats();

    $result = $validator->validate([
        'contacts' => [
            ['team_id' => 10],
            ['team_id' => 999],
        ],
    ]);

    expect($result->errors())->toHaveKey('contacts.1.team_id');
    expect($this->connection->getStats()['queries'])->toBe(1);
});

test('DBLayer SQLite provider handles mixed nested database rules', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $validator = Validator::make([
        'profile.team_id' => 'required|integer|exists:teams,id',
        'profile.email' => 'required|email|unique:users,email',
    ], $provider)->setFailFast(false);
    $this->connection->resetStats();

    $result = $validator->validate([
        'profile' => [
            'team_id' => 999,
            'email' => 'alice@example.com',
        ],
    ]);

    expect($result->errors())->toHaveKeys(['profile.team_id', 'profile.email'])
        ->and($this->connection->getStats()['queries'])->toBe(2);
});

test('DBLayer SQLite provider keeps logical batches intact at representative sizes', function (int $size) {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $contacts = array_fill(0, $size, ['team_id' => 10]);
    $this->connection->resetStats();

    $result = Validator::make([
        'contacts.*.team_id' => 'required|exists:teams,id',
    ], $provider)->validate(['contacts' => $contacts]);

    expect($result->passes())->toBeTrue()
        ->and($this->connection->getStats()['queries'])->toBe(1);
})->with([1, 2, 10, 100, 1000]);

test('DBLayer SQLite provider uses connection-derived sizing across representative unique batches', function () {
    $safeSize = $this->connection->safeBatchSize(requested: 128);
    $sizes = array_unique([1, 2, 10, $safeSize - 1, $safeSize, $safeSize + 1, 100, 1_000]);

    foreach ($sizes as $size) {
        $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
        $contacts = [];
        $this->connection->resetStats();

        foreach (range(1, $size) as $index) {
            $contacts[] = ['email' => "new-{$index}@example.com"];
        }

        $result = Validator::make([
            'contacts.*.email' => 'required|email|unique:users,email',
        ], $provider)->validate(['contacts' => $contacts]);

        expect($result->passes())->toBeTrue()
            ->and($this->connection->getStats()['queries'])->toBe((int) ceil($size / $safeSize));
    }
});

test('DBLayer SQLite provider uses connection-derived sizing across representative exists batches', function () {
    $safeSize = $this->connection->safeBatchSize(requested: 128);
    $sizes = array_unique([1, 2, 10, $safeSize - 1, $safeSize, $safeSize + 1, 100, 1_000]);

    foreach ($sizes as $size) {
        $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
        $checks = [];
        $this->connection->resetStats();

        foreach (range(1, $size) as $index) {
            $checks[] = ['id' => $index, 'column' => 'code', 'value' => "missing-{$index}"];
        }

        expect($provider->batchExists('teams', $checks))->toHaveCount($size)
            ->and($this->connection->getStats()['queries'])->toBe((int) ceil($size / $safeSize));
    }
});

test('DBLayer SQLite provider honors constrained bind limits with one fixed ignore binding', function () {
    $connection = DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'security' => ['max_params' => 32],
    ], 'reqshield-limited');
    $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
    $connection->insert('INSERT INTO users (id, email) VALUES (?, ?)', [1, 'candidate-1@example.com']);

    $provider = DBLayerDatabaseProvider::fromConnection($connection);
    $contacts = [];
    $connection->resetStats();
    foreach (range(1, 100) as $index) {
        $contacts[] = ['email' => "candidate-{$index}@example.com"];
    }

    $result = Validator::make([
        'contacts.*.email' => Rule::unique('users', 'email')->ignore('1'),
    ], $provider)->validate(['contacts' => $contacts]);
    $safeSize = $connection->safeBatchSize(
        parametersPerRow: 1,
        fixedBindings: 1,
        requested: 100,
    );

    expect($result->passes())->toBeTrue()
        ->and($safeSize)->toBe(31)
        ->and($connection->getStats()['queries'])->toBe((int) ceil(100 / $safeSize))
        ->and($safeSize + 1)->toBeLessThanOrEqual($connection->effectiveMaxBindParameters());
});

test('DBLayer SQLite provider groups duplicate values and separate columns correctly', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $contacts = array_fill(0, 10, [
        'email' => 'new@example.com',
        'username' => 'new-user',
    ]);
    $this->connection->resetStats();

    $result = Validator::make([
        'contacts.*.email' => 'required|unique:users,email',
        'contacts.*.username' => 'required|unique:users,username',
    ], $provider)->validate(['contacts' => $contacts]);

    expect($result->passes())->toBeTrue()
        ->and($this->connection->getStats()['queries'])->toBe(2);
});

test('DBLayer SQLite provider reports partial and repeated unique conflicts', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $this->connection->resetStats();
    $checks = [
        ['id' => 1, 'column' => 'email', 'value' => 'alice@example.com'],
        ['id' => 2, 'column' => 'email', 'value' => 'new@example.com'],
        ['id' => 3, 'column' => 'email', 'value' => 'zero@example.com'],
        ['id' => 4, 'column' => 'email', 'value' => 'alice@example.com'],
    ];
    $checks = array_map(
        static fn(array $check): array => $check + [
            'ignore' => null,
            'id_column' => 'id',
            'include_trashed' => true,
            'soft_delete_column' => null,
        ],
        $checks,
    );

    expect($provider->batchUnique('users', $checks))->toBe([1, 3, 4])
        ->and($this->connection->getStats()['queries'])->toBe(1);
});

test('DBLayer SQLite provider preserves SQL scalar and null semantics', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $checks = [];

    foreach ([0, '0', false, null, 'alpha'] as $index => $value) {
        $checks[] = [
            'id' => $index,
            'column' => 'token',
            'value' => $value,
        ];
    }

    expect($provider->batchExists('edge_values', $checks))->toBe([]);

    $uniqueChecks = array_map(
        static fn(array $check): array => $check + [
            'ignore' => null,
            'id_column' => 'id',
            'include_trashed' => false,
            'soft_delete_column' => 'deleted_at',
        ],
        $checks,
    );

    expect($provider->batchUnique('edge_values', $uniqueChecks))->toBe([0, 1, 2, 3, 4]);
});

test('DBLayer infrastructure failures remain distinct from validation misses', function () {
    $validator = Validator::make([
        'team_id' => 'exists:missing_table,id',
    ], DBLayerDatabaseProvider::fromConnection($this->connection));
    $exception = null;

    try {
        $validator->validate(['team_id' => 1]);
    } catch (DatabaseValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(DatabaseValidationException::class)
        ->and($exception?->getPrevious())->not->toBeNull();
});

test('DBLayer provider resolves the current execution connection for each operation', function () {
    $second = DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ], 'reqshield-second');
    $second->statement('CREATE TABLE teams (id INTEGER PRIMARY KEY, code TEXT)');
    $second->insert('INSERT INTO teams (id, code) VALUES (?, ?)', [20, 'secondary']);

    $current = $this->connection;
    $resolutions = 0;
    $provider = new DBLayerDatabaseProvider(
        static function () use (&$current, &$resolutions) {
            ++$resolutions;

            return $current;
        },
    );

    expect($provider->batchExists('teams', [
        ['id' => 1, 'field' => 'team_id', 'column' => 'id', 'value' => 10],
    ]))->toBe([]);

    $current = $second;

    expect($provider->batchExists('teams', [
        ['id' => 2, 'field' => 'team_id', 'column' => 'id', 'value' => 20],
    ]))->toBe([])
        ->and($provider->batchExists('teams', [
            ['id' => 3, 'field' => 'team_id', 'column' => 'id', 'value' => 10],
        ]))->toBe([3])
        ->and($resolutions)->toBe(3);
});

test('DBLayer provider resolves once per operation even when bind limits require chunks', function () {
    $connection = DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'security' => ['max_params' => 3],
    ], 'reqshield-resolver-once');
    $connection->statement('CREATE TABLE teams (id INTEGER PRIMARY KEY, code TEXT)');

    $resolutions = 0;
    $provider = new DBLayerDatabaseProvider(
        static function () use ($connection, &$resolutions) {
            ++$resolutions;

            return $connection;
        },
    );
    $checks = [];
    foreach (range(1, 7) as $id) {
        $checks[] = [
            'id' => $id,
            'field' => "team_{$id}",
            'column' => 'code',
            'value' => "missing-{$id}",
        ];
    }

    $connection->resetStats();

    expect($provider->batchExists('teams', $checks))->toHaveCount(7)
        ->and($resolutions)->toBe(1)
        ->and($connection->getStats()['queries'])->toBe(3);
});

test('DBLayer provider rejects unsafe identifiers before query execution', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);

    expect(fn() => $provider->batchExists('teams; DROP TABLE users', [
        ['id' => 1, 'field' => 'team_id', 'column' => 'id', 'value' => 10],
    ]))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $provider->batchExists('teams', [
            ['id' => 1, 'field' => 'team_id', 'column' => 'id) OR 1=1 --', 'value' => 10],
        ]))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $provider->batchUnique('users', [[
            'id' => 1,
            'field' => 'email',
            'column' => 'email',
            'value' => 'new@example.com',
            'ignore' => 1,
            'id_column' => 'id; DROP TABLE users',
            'include_trashed' => true,
            'soft_delete_column' => null,
        ]]))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $provider->batchUnique('users', [[
            'id' => 1,
            'field' => 'email',
            'column' => 'email',
            'value' => 'new@example.com',
            'ignore' => null,
            'id_column' => 'id',
            'include_trashed' => false,
            'soft_delete_column' => 'deleted_at) OR 1=1 --',
        ]]))->toThrow(InvalidArgumentException::class);
});

test('non-database validation does not resolve the DBLayer connection', function () {
    $resolutions = 0;
    $provider = new DBLayerDatabaseProvider(
        static function () use (&$resolutions) {
            ++$resolutions;

            throw new RuntimeException('resolver must stay cold');
        },
    );

    $result = Validator::make([
        'email' => 'required|email',
    ], $provider)->validate([
        'email' => 'valid@example.com',
    ]);

    expect($result->passes())->toBeTrue()
        ->and($resolutions)->toBe(0);
});

test('DBLayer provider correlates database collation and numeric comparisons without PHP equality', function () {
    $this->connection->statement('CREATE TABLE comparison_values (id INTEGER PRIMARY KEY, token TEXT COLLATE NOCASE)');
    $this->connection->insert('INSERT INTO comparison_values (id, token) VALUES (?, ?)', [1, 'Alice@example.com']);
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection, maxBatchValues: 2);
    $checks = [
        ['id' => 0, 'column' => 'token', 'value' => 'alice@example.com'],
        ['id' => 1, 'column' => 'token', 'value' => 'ALICE@example.com'],
        ['id' => 2, 'column' => 'token', 'value' => 'missing@example.com'],
        ['id' => 3, 'column' => 'token', 'value' => 'alice@example.com'],
        ['id' => 4, 'column' => 'id', 'value' => '01'],
        ['id' => 5, 'column' => 'id', 'value' => '1.0'],
    ];

    expect($provider->batchExists('comparison_values', $checks))->toBe([2])
        ->and($provider->batchUnique('comparison_values', $checks))->toBe([0, 1, 3, 4, 5]);

    $ignored = array_map(static fn(array $check): array => $check + ['ignore' => 1], $checks);
    expect($provider->batchUnique('comparison_values', $ignored))->toBe([]);
});

test('DBLayer provider accepts qualified columns with null ignore and soft delete predicates', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $checks = [
        ['id' => 0, 'column' => 'edge_values.token', 'value' => 'alpha'],
        ['id' => 1, 'column' => 'edge_values.token', 'value' => null],
        ['id' => 2, 'column' => 'edge_values.token', 'value' => 'missing'],
    ];
    $unique = array_map(static fn(array $check): array => $check + [
        'id_column' => 'edge_values.id',
        'soft_delete_column' => 'edge_values.deleted_at',
        'include_trashed' => false,
        'ignore' => 3,
    ], $checks);

    expect($provider->batchExists('edge_values', $checks))->toBe([2])
        ->and($provider->batchUnique('edge_values', $unique))->toBe([1]);
});

test('DBLayer provider preserves binding types and treats hostile candidate text as data', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection);
    $checks = [];
    foreach ([0, '0', false, 0.0, '00', "alpha' OR 1=1 --", 'alpha'] as $id => $value) {
        $checks[] = ['id' => $id, 'column' => 'token', 'value' => $value];
    }

    $missing = [];
    $existing = [];
    foreach ($checks as $check) {
        $matched = $this->connection->table('edge_values')->where('token', '=', $check['value'])->exists();
        if ($matched) {
            $existing[] = $check['id'];
        } else {
            $missing[] = $check['id'];
        }
    }

    expect($provider->batchExists('edge_values', $checks))->toBe($missing)
        ->and($provider->batchUnique('edge_values', $checks))->toBe($existing);
});

test('DBLayer provider query width is configurable and duplicate candidates stay deduplicated', function () {
    $provider = DBLayerDatabaseProvider::fromConnection($this->connection, maxBatchValues: 2);
    $checks = [];
    foreach (['a', 'b', 'c', 'a', 'b'] as $id => $value) {
        $checks[] = ['id' => $id, 'column' => 'token', 'value' => $value];
    }
    $this->connection->resetStats();

    expect($provider->batchExists('edge_values', $checks))->toBe([0, 1, 2, 3, 4])
        ->and($this->connection->getStats()['queries'])->toBe(2)
        ->and(fn() => DBLayerDatabaseProvider::fromConnection($this->connection, maxBatchValues: 0))
        ->toThrow(InvalidArgumentException::class);
});

test('DBLayer provider honors restricted raw SQL policies without changing comparison semantics', function (array $security) {
    $connection = DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'security' => $security,
    ], 'reqshield-restricted-raw');
    $connection->statement('CREATE TABLE tokens (id INTEGER PRIMARY KEY, token TEXT COLLATE NOCASE)');
    $connection->insert('INSERT INTO tokens (id, token) VALUES (?, ?)', [1, 'ALPHA']);
    $provider = DBLayerDatabaseProvider::fromConnection($connection);
    $checks = [
        ['id' => 0, 'column' => 'tokens.token', 'value' => 'alpha'],
        ['id' => 1, 'column' => 'tokens.token', 'value' => 'missing'],
    ];

    expect($provider->batchExists('tokens', $checks))->toBe([1])
        ->and($provider->batchUnique('tokens', $checks))->toBe([0]);
})->with([
    [['raw_sql_policy' => 'deny']],
    [['raw_sql_policy' => 'allowlist', 'raw_sql_allowlist' => ['/^COUNT/']]],
]);
