<?php

declare(strict_types=1);

use Infocyph\DBLayer\DB;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;
use Infocyph\ReqShield\Rule;
use Infocyph\ReqShield\Tests\Integration\Database\DBLayerDatabaseProvider;
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
    $provider = new DBLayerDatabaseProvider($this->connection);
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
    $provider = new DBLayerDatabaseProvider($this->connection);

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
    $provider = new DBLayerDatabaseProvider($this->connection);
    $validator = Validator::make([
        'contacts.*.team_id' => 'required|exists:teams,id',
    ], $provider);

    $result = $validator->validate([
        'contacts' => [
            ['team_id' => 10],
            ['team_id' => 999],
        ],
    ]);

    expect($result->errors())->toHaveKey('contacts.1.team_id');
    expect($provider->operations)->toBe(1);
});

test('DBLayer SQLite provider handles mixed nested database rules', function () {
    $provider = new DBLayerDatabaseProvider($this->connection);
    $validator = Validator::make([
        'profile.team_id' => 'required|integer|exists:teams,id',
        'profile.email' => 'required|email|unique:users,email',
    ], $provider)->setFailFast(false);

    $result = $validator->validate([
        'profile' => [
            'team_id' => 999,
            'email' => 'alice@example.com',
        ],
    ]);

    expect($result->errors())->toHaveKeys(['profile.team_id', 'profile.email'])
        ->and($provider->operations)->toBe(2);
});

test('DBLayer SQLite provider keeps logical batches intact at representative sizes', function (int $size) {
    $provider = new DBLayerDatabaseProvider($this->connection);
    $contacts = array_fill(0, $size, ['team_id' => 10]);

    $result = Validator::make([
        'contacts.*.team_id' => 'required|exists:teams,id',
    ], $provider)->validate(['contacts' => $contacts]);

    expect($result->passes())->toBeTrue()
        ->and($provider->operations)->toBe(1);
})->with([1, 2, 10, 100, 1000]);

test('DBLayer SQLite provider uses connection-derived sizing across representative unique batches', function () {
    $safeSize = $this->connection->safeBatchSize(requested: 1_000);
    $sizes = array_unique([1, 2, 10, $safeSize - 1, $safeSize, $safeSize + 1, 100, 1_000]);

    foreach ($sizes as $size) {
        $provider = new DBLayerDatabaseProvider($this->connection);
        $contacts = [];

        foreach (range(1, $size) as $index) {
            $contacts[] = ['email' => "new-{$index}@example.com"];
        }

        $result = Validator::make([
            'contacts.*.email' => 'required|email|unique:users,email',
        ], $provider)->validate(['contacts' => $contacts]);

        expect($result->passes())->toBeTrue()
            ->and($provider->operations)->toBe((int) ceil($size / $safeSize));
    }
});

test('DBLayer SQLite provider uses connection-derived sizing across representative exists batches', function () {
    $safeSize = $this->connection->safeBatchSize(requested: 1_000);
    $sizes = array_unique([1, 2, 10, $safeSize - 1, $safeSize, $safeSize + 1, 100, 1_000]);

    foreach ($sizes as $size) {
        $provider = new DBLayerDatabaseProvider($this->connection);
        $checks = [];

        foreach (range(1, $size) as $index) {
            $checks[] = ['id' => $index, 'column' => 'code', 'value' => "missing-{$index}"];
        }

        expect($provider->batchExists('teams', $checks))->toHaveCount($size)
            ->and($provider->operations)->toBe((int) ceil($size / $safeSize));
    }
});

test('DBLayer SQLite provider honors constrained bind limits and fixed ignore bindings', function () {
    $connection = DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'security' => ['max_params' => 32],
    ], 'reqshield-limited');
    $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
    $connection->insert('INSERT INTO users (id, email) VALUES (?, ?)', [1, 'candidate-1@example.com']);

    $provider = new DBLayerDatabaseProvider($connection);
    $contacts = [];
    foreach (range(1, 100) as $index) {
        $contacts[] = ['email' => "candidate-{$index}@example.com"];
    }

    $result = Validator::make([
        'contacts.*.email' => Rule::unique('users', 'email')->ignore('1'),
    ], $provider)->validate(['contacts' => $contacts]);
    $safeSize = $connection->safeBatchSize(fixedBindings: 1, requested: 100);

    expect($result->passes())->toBeTrue()
        ->and($safeSize)->toBe(31)
        ->and($provider->operations)->toBe((int) ceil(100 / $safeSize))
        ->and(array_all(
            $provider->bindingCounts,
            static fn(int $bindings): bool => $bindings <= $connection->effectiveMaxBindParameters(),
        ))->toBeTrue();
});

test('DBLayer SQLite provider groups duplicate values and separate columns correctly', function () {
    $provider = new DBLayerDatabaseProvider($this->connection);
    $contacts = array_fill(0, 10, [
        'email' => 'new@example.com',
        'username' => 'new-user',
    ]);

    $result = Validator::make([
        'contacts.*.email' => 'required|unique:users,email',
        'contacts.*.username' => 'required|unique:users,username',
    ], $provider)->validate(['contacts' => $contacts]);

    expect($result->passes())->toBeTrue()
        ->and($provider->operations)->toBe(2);
});

test('DBLayer SQLite provider reports partial and repeated unique conflicts', function () {
    $provider = new DBLayerDatabaseProvider($this->connection);
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
        ->and($provider->operations)->toBe(1);
});

test('DBLayer SQLite provider preserves SQL scalar and null semantics', function () {
    $provider = new DBLayerDatabaseProvider($this->connection);
    $checks = [];

    foreach ([0, '0', false, null, 'alpha'] as $index => $value) {
        $checks[] = [
            'id' => "check-{$index}",
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

    expect($provider->batchUnique('edge_values', $uniqueChecks))->toBe([
        'check-0',
        'check-1',
        'check-2',
        'check-3',
        'check-4',
    ]);
});

test('DBLayer infrastructure failures remain distinct from validation misses', function () {
    $validator = Validator::make([
        'team_id' => 'exists:missing_table,id',
    ], new DBLayerDatabaseProvider($this->connection));
    $exception = null;

    try {
        $validator->validate(['team_id' => 1]);
    } catch (DatabaseValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(DatabaseValidationException::class)
        ->and($exception?->getPrevious())->not->toBeNull();
});
