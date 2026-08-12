<?php

declare(strict_types=1);

use Infocyph\DBLayer\DB;
use Infocyph\ReqShield\Rule;
use Infocyph\ReqShield\Tests\Integration\Database\DBLayerDatabaseProvider;
use Infocyph\ReqShield\Validator;

beforeEach(function () {
    DB::resetRuntimeState();
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
});

afterEach(function () {
    DB::resetRuntimeState();
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
})->with([1, 10, 100, 401, 1000]);

test('DBLayer SQLite provider keeps unique batches intact at representative sizes', function (int $size) {
    $provider = new DBLayerDatabaseProvider($this->connection);
    $contacts = [];

    foreach (range(1, $size) as $index) {
        $contacts[] = ['email' => "new-{$index}@example.com"];
    }

    $result = Validator::make([
        'contacts.*.email' => 'required|email|unique:users,email',
    ], $provider)->validate(['contacts' => $contacts]);

    expect($result->passes())->toBeTrue()
        ->and($provider->operations)->toBe((int) ceil($size / 400));
})->with([1, 10, 100, 401, 1000]);

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

test('DBLayer SQLite provider preserves SQL scalar and null semantics', function () {
    $provider = new DBLayerDatabaseProvider($this->connection);
    $checks = [];

    foreach ([0, '0', false, null] as $index => $value) {
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
    ]);
});
