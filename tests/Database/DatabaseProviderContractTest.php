<?php

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Validator;

test('database provider contract uses structured batch payloads for all providers', function () {
    $provider = new class implements DatabaseProvider {
        public array $existsPayloads = [];
        public array $uniquePayloads = [];
        public int $queryCalls = 0;

        public function batchExistsCheck(string $table, array $checks): array
        {
            $this->existsPayloads[] = ['table' => $table, 'checks' => $checks];

            $missing = [];
            foreach ($checks as $check) {
                if (($check['value'] ?? null) === 9999) {
                    $missing[] = $check['field'];
                }
            }

            return $missing;
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            $this->uniquePayloads[] = ['table' => $table, 'checks' => $checks];

            $taken = [];
            foreach ($checks as $check) {
                if (($check['value'] ?? null) === 'taken@example.com') {
                    $taken[] = $check['field'];
                }
            }

            return $taken;
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function query(string $query, array $params = []): array
        {
            $this->queryCalls++;

            return [];
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
    expect($provider->queryCalls)->toBe(0);
    expect($provider->uniquePayloads)->toHaveCount(1);
    expect($provider->existsPayloads)->toHaveCount(1);
    expect($provider->uniquePayloads[0]['checks'][0])->toHaveKeys([
        'column',
        'value',
        'field',
        'ignore_id',
        'id_column',
        'with_trashed',
        'soft_delete_column',
    ]);
    expect($provider->existsPayloads[0]['checks'][0])->toHaveKeys([
        'column',
        'value',
        'field',
    ]);
});

test('batch execution does not fall back to query when provider batch methods throw', function () {
    $provider = new class implements DatabaseProvider {
        public int $queryCalls = 0;

        public function batchExistsCheck(string $table, array $checks): array
        {
            throw new RuntimeException('batch exists unavailable');
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            throw new RuntimeException('batch unique unavailable');
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function query(string $query, array $params = []): array
        {
            $this->queryCalls++;

            return [];
        }
    };

    $validator = Validator::make([
        'email' => 'required|email|unique:users,email',
    ], $provider);

    expect(fn () => $validator->validate([
        'email' => 'taken@example.com',
    ]))->toThrow(RuntimeException::class, 'batch unique unavailable');
    expect($provider->queryCalls)->toBe(0);
});
