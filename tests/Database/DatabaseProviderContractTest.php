<?php

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\NativeBatchDatabaseProvider;
use Infocyph\ReqShield\Validator;

test('native batch provider contract uses structured payloads and query fallback is skipped', function () {
    $provider = new class implements NativeBatchDatabaseProvider {
        public array $existsPayloads = [];
        public array $uniquePayloads = [];

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
            throw new RuntimeException('Native batch path should not call query()');
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

test('non-native provider contract uses query fallback path', function () {
    $provider = new class implements DatabaseProvider {
        public array $queries = [];
        public int $batchExistsCalls = 0;
        public int $batchUniqueCalls = 0;

        protected array $tables = [
            'users' => [
                ['id' => 1, 'email' => 'taken@example.com', 'deleted_at' => null],
            ],
            'teams' => [
                ['id' => 10, 'code' => 'ENG'],
            ],
        ];

        public function batchExistsCheck(string $table, array $checks): array
        {
            $this->batchExistsCalls++;

            return [];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            $this->batchUniqueCalls++;

            return [];
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
            $this->queries[] = ['query' => $query, 'params' => $params];

            preg_match('/FROM\s+`?([a-zA-Z0-9_]+)`?/i', $query, $tableMatch);
            preg_match('/WHERE\s+`?([a-zA-Z0-9_]+)`?\s+IN/i', $query, $columnMatch);
            $table = $tableMatch[1] ?? '';
            $column = $columnMatch[1] ?? '';

            if ($table === '' || $column === '' || !isset($this->tables[$table])) {
                return [];
            }

            $rows = [];
            foreach ($this->tables[$table] as $row) {
                if (in_array($row[$column] ?? null, $params, true)) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }
    };

    $validator = Validator::make([
        'email' => 'required|email|unique:users,email',
        'team_code' => 'required|exists:teams,code',
    ], $provider)->setFailFast(false);

    $result = $validator->validate([
        'email' => 'taken@example.com',
        'team_code' => 'MISSING',
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKeys(['email', 'team_code']);
    expect($provider->queries)->not->toBeEmpty();
    expect($provider->batchExistsCalls)->toBe(0);
    expect($provider->batchUniqueCalls)->toBe(0);
});

test('native provider gracefully falls back to query when batch api throws', function () {
    $provider = new class implements NativeBatchDatabaseProvider {
        public int $queryCalls = 0;

        protected array $tables = [
            'users' => [
                ['id' => 1, 'email' => 'taken@example.com', 'deleted_at' => null],
            ],
        ];

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

            preg_match('/FROM\s+`?([a-zA-Z0-9_]+)`?/i', $query, $tableMatch);
            preg_match('/WHERE\s+`?([a-zA-Z0-9_]+)`?\s+IN/i', $query, $columnMatch);
            $table = $tableMatch[1] ?? '';
            $column = $columnMatch[1] ?? '';

            if ($table === '' || $column === '' || !isset($this->tables[$table])) {
                return [];
            }

            $rows = [];
            foreach ($this->tables[$table] as $row) {
                if (in_array($row[$column] ?? null, $params, true)) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }
    };

    $validator = Validator::make([
        'email' => 'required|email|unique:users,email',
    ], $provider);

    $result = $validator->validate([
        'email' => 'taken@example.com',
    ]);

    expect($result->fails())->toBeTrue();
    expect($result->errors())->toHaveKey('email');
    expect($provider->queryCalls)->toBeGreaterThan(0);
});
