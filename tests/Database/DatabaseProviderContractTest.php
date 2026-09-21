<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;
use Infocyph\ReqShield\Validator;

test('database provider contract uses structured batch payloads for all providers', function () {
    $provider = new class implements DatabaseProvider {
        /** @var array<int, array<int, mixed>> */
        public array $contractCalls = [];
        public array $existsPayloads = [];
        public array $uniquePayloads = [];

        public function batchExists(string $table, array $checks): array
        {
            $this->contractCalls[] = ['batchExists', $table, $checks];
            $this->existsPayloads[] = ['table' => $table, 'checks' => $checks];

            $missing = [];
            foreach ($checks as $check) {
                if (($check['value'] ?? null) === 9999) {
                    $missing[] = $check['id'];
                }
            }

            return $missing;
        }

        public function batchUnique(string $table, array $checks): array
        {
            $this->contractCalls[] = ['batchUnique', $table, $checks];
            $this->uniquePayloads[] = ['table' => $table, 'checks' => $checks];

            $taken = [];
            foreach ($checks as $check) {
                if (($check['value'] ?? null) === 'taken@example.com') {
                    $taken[] = $check['id'];
                }
            }

            return $taken;
        }

    };

    $validator = Validator::make([
        'email' => 'required|email|unique:users,email',
        'team_id' => 'required|exists:teams,id',
    ], $provider)->setFailFast(false);

    $result = $validator->validate([
        'email' => 'taken@example.com',
        'team_id' => 9999,
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['email', 'team_id']);
    expect($provider->uniquePayloads)->toHaveCount(1);
    expect($provider->existsPayloads)->toHaveCount(1);
    expect($provider->uniquePayloads[0]['checks'][0])->toHaveKeys([
        'id',
        'field',
        'column',
        'value',
        'ignore',
        'id_column',
        'include_trashed',
        'soft_delete_column',
    ]);
    expect($provider->existsPayloads[0]['checks'][0])->toHaveKeys([
        'id',
        'field',
        'column',
        'value',
    ]);
});

test('batch execution does not fall back to query when provider batch methods throw', function () {
    $provider = new class implements DatabaseProvider {
        /** @var array<int, array<int, mixed>> */
        public array $contractCalls = [];

        public function batchExists(string $table, array $checks): array
        {
            $this->contractCalls[] = ['batchExists', $table, $checks];
            throw new RuntimeException('batch exists unavailable');
        }

        public function batchUnique(string $table, array $checks): array
        {
            $this->contractCalls[] = ['batchUnique', $table, $checks];
            throw new RuntimeException('batch unique unavailable');
        }

    };

    $validator = Validator::make([
        'email' => 'required|email|unique:users,email',
    ], $provider);

    expect(fn () => $validator->validate([
        'email' => 'taken@example.com',
    ]))->toThrow(DatabaseValidationException::class, "Database validation failed for table 'users'.");
});


test('database provider rejects string correlation IDs', function () {
    $provider = new class implements DatabaseProvider {
        public function batchExists(string $table, array $checks): array
        {
            unset($table);

            return [(string) $checks[0]['id']];
        }

        public function batchUnique(string $table, array $checks): array
        {
            unset($table, $checks);

            return [];
        }
    };

    expect(fn () => Validator::make([
        'team_id' => 'required|exists:teams,id',
    ], $provider)->validate([
        'team_id' => 1,
    ]))->toThrow(
        DatabaseValidationException::class,
        'Database provider returned an unknown or malformed check ID.',
    );
});

test('database provider rejects duplicate correlation IDs', function () {
    $provider = new class implements DatabaseProvider {
        public function batchExists(string $table, array $checks): array
        {
            unset($table);
            $id = $checks[0]['id'];

            return [$id, $id];
        }

        public function batchUnique(string $table, array $checks): array
        {
            unset($table, $checks);

            return [];
        }
    };

    expect(fn () => Validator::make([
        'team_id' => 'required|exists:teams,id',
    ], $provider)->validate([
        'team_id' => 1,
    ]))->toThrow(
        DatabaseValidationException::class,
        'Database provider returned a duplicate check ID.',
    );
});

test('database correlation IDs do not inherit caller batch keys', function () {
    $provider = new class implements DatabaseProvider {
        /** @var list<int> */
        public array $ids = [];

        public function batchExists(string $table, array $checks): array
        {
            unset($table);
            $this->ids = array_column($checks, 'id');

            return [];
        }

        public function batchUnique(string $table, array $checks): array
        {
            unset($table, $checks);

            return [];
        }
    };

    $executor = new \Infocyph\ReqShield\Executors\BatchExecutor($provider);
    $errors = [];
    $executor->executeBatch([
        10 => [
            'rule' => new \Infocyph\ReqShield\Rules\Exists('teams', 'id'),
            'value' => 1,
            'field' => 'first',
        ],
        50 => [
            'rule' => new \Infocyph\ReqShield\Rules\Exists('teams', 'id'),
            'value' => 2,
            'field' => 'second',
        ],
    ], $errors);

    expect($provider->ids)->toBe([0, 1]);
});
